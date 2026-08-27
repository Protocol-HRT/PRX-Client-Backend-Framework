<?php

namespace App\Services\Kb;

use RuntimeException;

/**
 * Reads rows out of a mysqldump / phpMyAdmin `.sql` file without a database.
 *
 * The alternative was "load the dump into a scratch schema first, then read it
 * over a second connection". That works, but it makes a two-step manual
 * prerequisite out of what should be one repeatable command, and it puts a
 * loose copy of the source data on the server between the two steps. Parsing
 * the file directly keeps the import reproducible anywhere the file is.
 *
 * The grammar this handles is the one mysqldump emits, and only that:
 *
 *     INSERT INTO `table` (`a`, `b`) VALUES (1, 'x'), (2, NULL);
 *
 * Values are NULL, a bare number, or a single-quoted string using MySQL's
 * backslash escapes. Multiple rows per statement and multiple statements per
 * file are both normal — this dump has 106 rows across 103 statements, which
 * is why counting `(`-tuples with a regex gives the wrong answer (it also
 * counts each statement's column list).
 *
 * Not a general SQL parser: it deliberately understands nothing else in the
 * file and skips every other statement, including the CREATE TABLE.
 */
class SqlDumpReader
{
    /**
     * MySQL's backslash escapes, as they appear inside a quoted literal.
     *
     * `\%` and `\_` are deliberately absent: MySQL keeps the backslash for
     * those (they are only special to LIKE), so resolving them here would
     * silently alter the text. See readQuoted() for how they pass through.
     */
    private const ESCAPES = [
        '\\0' => "\0",
        '\\b' => "\x08",
        '\\n' => "\n",
        '\\r' => "\r",
        '\\t' => "\t",
        '\\Z' => "\x1A",
        '\\\\' => '\\',
        "\\'" => "'",
        '\\"' => '"',
    ];

    /** Escapes MySQL emits but does not resolve — the backslash is literal. */
    private const LITERAL_BACKSLASH_ESCAPES = ['\\%', '\\_'];

    /**
     * Yields every INSERTed row for one table as a column => value map.
     *
     * A generator because the source is a few megabytes of longtext and there
     * is no reason to hold all of it plus the parsed copy at once.
     *
     * @return \Generator<int, array<string, string|null>>
     */
    public function rows(string $path, string $table): \Generator
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Dump file not readable: {$path}");
        }

        $sql = file_get_contents($path);

        if ($sql === false) {
            throw new RuntimeException("Could not read dump file: {$path}");
        }

        $needle = 'INSERT INTO `'.$table.'` (';
        $offset = 0;

        while (($start = strpos($sql, $needle, $offset)) !== false) {
            $cursor = $start + strlen($needle);

            $columns = $this->readColumnList($sql, $cursor);
            $cursor = $this->skipTo($sql, $cursor, 'VALUES');

            foreach ($this->readTuples($sql, $cursor) as $values) {
                if (count($values) !== count($columns)) {
                    throw new RuntimeException(sprintf(
                        'Column/value count mismatch in %s near byte %d: %d columns, %d values.',
                        $table, $start, count($columns), count($values)
                    ));
                }

                yield array_combine($columns, $values);
            }

            $offset = $cursor;
        }
    }

    /**
     * Reads `` `a`, `b`) `` starting just after the opening paren, leaving the
     * cursor immediately after the closing paren.
     *
     * @return list<string>
     */
    private function readColumnList(string $sql, int &$cursor): array
    {
        $end = strpos($sql, ')', $cursor);

        if ($end === false) {
            throw new RuntimeException('Unterminated column list in dump.');
        }

        $columns = array_map(
            static fn (string $c): string => trim(trim($c), '`'),
            explode(',', substr($sql, $cursor, $end - $cursor))
        );

        $cursor = $end + 1;

        return $columns;
    }

    private function skipTo(string $sql, int $cursor, string $keyword): int
    {
        $at = stripos($sql, $keyword, $cursor);

        if ($at === false) {
            throw new RuntimeException("Expected {$keyword} in INSERT statement.");
        }

        return $at + strlen($keyword);
    }

    /**
     * Reads `(…), (…);` from the cursor, leaving it after the terminating `;`.
     *
     * @return \Generator<int, list<string|null>>
     */
    private function readTuples(string $sql, int &$cursor): \Generator
    {
        $length = strlen($sql);

        while ($cursor < $length) {
            // Between tuples: whitespace, a comma, or the statement's `;`.
            $char = $sql[$cursor];

            if ($char === ';') {
                $cursor++;

                return;
            }

            if ($char === ',' || ctype_space($char)) {
                $cursor++;

                continue;
            }

            if ($char !== '(') {
                throw new RuntimeException(sprintf(
                    'Unexpected character %s at byte %d; expected a value tuple.',
                    var_export($char, true), $cursor
                ));
            }

            $cursor++;
            yield $this->readTuple($sql, $cursor);
        }

        throw new RuntimeException('Dump ended inside an INSERT statement.');
    }

    /**
     * Reads the values of one tuple, cursor starting just after `(` and
     * ending just after the matching `)`.
     *
     * @return list<string|null>
     */
    private function readTuple(string $sql, int &$cursor): array
    {
        $values = [];
        $length = strlen($sql);

        while ($cursor < $length) {
            while ($cursor < $length && ctype_space($sql[$cursor])) {
                $cursor++;
            }

            $char = $sql[$cursor];

            if ($char === ')') {
                $cursor++;

                return $values;
            }

            if ($char === ',') {
                $cursor++;

                continue;
            }

            $values[] = $char === "'"
                ? $this->readQuoted($sql, $cursor)
                : $this->readBare($sql, $cursor);
        }

        throw new RuntimeException('Dump ended inside a value tuple.');
    }

    /**
     * Reads a single-quoted literal, cursor on the opening quote.
     *
     * Escapes are resolved as MySQL wrote them. Note `''` is NOT treated as an
     * escaped quote: mysqldump always emits `\'`, and accepting both would make
     * a literal empty string followed by a string indistinguishable from one
     * value.
     */
    private function readQuoted(string $sql, int &$cursor): string
    {
        $cursor++; // opening quote
        $out = '';
        $length = strlen($sql);

        while ($cursor < $length) {
            $char = $sql[$cursor];

            if ($char === '\\') {
                if ($cursor + 1 >= $length) {
                    // A file truncated mid-escape. Without this the next read
                    // is out of range and the command dies with a PHP notice
                    // and a stack trace instead of its own error message.
                    throw new RuntimeException('Dump ended inside an escape sequence.');
                }

                $pair = substr($sql, $cursor, 2);

                $out .= match (true) {
                    isset(self::ESCAPES[$pair]) => self::ESCAPES[$pair],
                    in_array($pair, self::LITERAL_BACKSLASH_ESCAPES, true) => $pair,
                    // An escape MySQL does not define: the backslash is
                    // dropped and the character stands, which is what the
                    // server itself does.
                    default => $sql[$cursor + 1],
                };

                $cursor += 2;

                continue;
            }

            if ($char === "'") {
                $cursor++;

                return $out;
            }

            $out .= $char;
            $cursor++;
        }

        throw new RuntimeException('Dump ended inside a quoted string.');
    }

    /** Reads NULL or a bare numeric, cursor on its first character. */
    private function readBare(string $sql, int &$cursor): ?string
    {
        $start = $cursor;
        $length = strlen($sql);

        while ($cursor < $length && ! in_array($sql[$cursor], [',', ')'], true)) {
            $cursor++;
        }

        $raw = trim(substr($sql, $start, $cursor - $start));

        return strcasecmp($raw, 'NULL') === 0 ? null : $raw;
    }
}

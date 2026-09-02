# Quiz Import

How to seed and manage the intake quiz via the database instead of hardcoding it.

## Overview

The quiz is defined as a `QuizSchema` in the database (Quiz → Steps → Questions → Options). The backend serves it via `GET /api/v1/quiz` and the frontend renders it automatically.

The default quiz ("IGNITE Intake Assessment") ships as a JSON file that can be imported with a single Artisan command.

## Files

| File | Purpose |
|------|---------|
| `database/seeders/data/default-quiz.json` | Default quiz definition |
| `app/Console/Commands/Quiz/ImportQuizCommand.php` | Artisan import command |

## Usage

### Import the default quiz

```bash
php artisan quiz:import
```

### Dry run (preview without writing)

```bash
php artisan quiz:import --dry-run
```

### Import a custom JSON file

```bash
php artisan quiz:import path/to/custom-quiz.json
```

### Force recreate (delete existing + reimport)

```bash
php artisan quiz:import --force
```

## JSON Structure

```json
{
  "name": "Quiz Name",
  "slug": "quiz-slug",
  "description": "Optional description",
  "is_active": true,
  "is_default": true,
  "steps": [
    {
      "slug": "step-slug",
      "name": "Step Name",
      "heading": "Question shown at top of screen",
      "description": "Reassurance text below heading",
      "position": 0,
      "visible_when": [],
      "questions": [
        {
          "slug": "question-slug",
          "kind": "single_select",
          "prompt": "The question text",
          "help": "Optional help line",
          "is_required": true,
          "position": 0,
          "visible_when": [],
          "config": null,
          "options": [
            {
              "value": "option-value",
              "label": "Option Label",
              "description": "Optional description",
              "icon": "tabler-icon-name",
              "is_exclusive": false,
              "price_source": null,
              "position": 0
            }
          ]
        }
      ]
    }
  ]
}
```

## Question Kinds

| Kind | Description | Has Options? |
|------|-------------|:------------:|
| `sex` | Binary male/female selector (reserved) | No |
| `age` | Numeric slider with bounds | No |
| `health_goals` | Multi-select grid from `health_goals` table (reserved) | No |
| `single_select` | Radio-style single choice | **Yes** |
| `multi_select` | Checkbox-style multi choice | **Yes** |
| `scale` | Numeric slider (min/max/default in config) | No |
| `measurement` | Height/weight with unit conversion | No |
| `text` | Free-form textarea | No |
| `contact` | Name, email, phone, consent checkboxes (reserved) | No |

Reserved kinds (`sex`, `age`, `health_goals`, `contact`) read from existing data sources — authored options are ignored.

## Conditional Visibility

Steps and questions can be conditionally shown based on previous answers:

```json
{
  "visible_when": [
    {
      "field": "health_goals",
      "operator": "contains",
      "value": "fat-loss"
    }
  ]
}
```

### Operators

| Operator | Behavior |
|----------|----------|
| `equals` | Scalar answer equals value |
| `not_equals` | Scalar answer does not equal value |
| `contains` | Array answer contains value |
| `not_contains` | Array answer does not contain value |

Multiple conditions are ANDed together.

## Config Examples

### Age slider

```json
{
  "config": {
    "min": 18,
    "max": 100,
    "default": 32
  }
}
```

### Measurement (height)

```json
{
  "config": {
    "unit": "imperial",
    "measure": "height",
    "min_cm": 137,
    "max_cm": 213,
    "default_cm": 178
  }
}
```

### Contact form

```json
{
  "config": {
    "legal": "By continuing you agree to...",
    "cta_label": "Unlock My Protocol",
    "name_label": "Full name",
    "email_label": "Email address",
    "phone_label": "Phone (optional)",
    "phone_required": false,
    "sms_consent_label": "Text me protocol updates",
    "email_consent_label": "Email me my full protocol + clinical notes"
  }
}
```

## Price Sources

Options on `single_select` and `multi_select` questions can link to live pricing:

| Value | Behavior |
|-------|----------|
| `null` | No price shown |
| `"products"` | Price range from published product plans |
| `"packages:protocol"` | Price range from published "protocol" tier packages |
| `"packages:stack"` | Price range from published "stack" tier packages |
| `"packages"` | Price range from any published package |

Prices are computed live by `QuizSchemaBuilder` — never stored in the option.

## Re-importing

The command is **re-runnable**. It keys on quiz slug:

- **Same slug** → updates the existing quiz (replaces steps, questions, options)
- **`--force`** → deletes the existing quiz and creates a new one
- **Different slug** → creates a second quiz

Only one quiz can be `is_default` at a time — setting a new default clears the flag on others.

## Docker Production

### Rebuild and import

```bash
# Rebuild the image with latest code
docker compose -f docker-compose.prod.yml build --no-cache app
docker compose -f docker-compose.prod.yml up -d

# Dry run
docker compose -f docker-compose.prod.yml exec app \
  php artisan quiz:import --dry-run

# Import
docker compose -f docker-compose.prod.yml exec app \
  php artisan quiz:import
```

### One-off copy without rebuild

```bash
# Copy file into running container
docker compose -f docker-compose.prod.yml cp \
  database/seeders/data/default-quiz.json \
  app:/var/www/html/database/seeders/data/default-quiz.json

# Import
docker compose -f docker-compose.prod.yml exec app \
  php artisan quiz:import
```

This works for quick fixes but won't survive a container rebuild.

## Customizing the Quiz

1. Edit `database/seeders/data/default-quiz.json`
2. Run `php artisan quiz:import` (or `--force` to replace entirely)
3. Test with `GET /api/v1/quiz` — the response should reflect your changes
4. The frontend picks up changes on next page load (no frontend redeploy needed)

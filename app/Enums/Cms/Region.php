<?php

namespace App\Enums\Cms;

/**
 * Fixed region slots for global site chrome. The frontend expects exactly
 * these keys in the /api/v1/layout payload — adding a region is a code
 * change shipped together with the frontend component that renders it.
 */
enum Region: string
{
    case TopBar = 'top_bar';
    case Header = 'header';
    case PreFooter = 'pre_footer';
    case Footer = 'footer';
    case SidebarLeft = 'sidebar_left';
    case SidebarRight = 'sidebar_right';

    public function label(): string
    {
        return match ($this) {
            self::TopBar => 'Top bar',
            self::Header => 'Header',
            self::PreFooter => 'Pre-footer',
            self::Footer => 'Footer',
            self::SidebarLeft => 'Left sidebar',
            self::SidebarRight => 'Right sidebar',
        };
    }
}

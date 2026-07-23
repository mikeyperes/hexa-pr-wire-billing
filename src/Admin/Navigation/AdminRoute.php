<?php

namespace HexaPrWire\Billing\Admin\Navigation;

final class AdminRoute {
    public function __construct( private string $area, private string $section ) {
    }

    public function area(): string {
        return $this->area;
    }

    public function section(): string {
        return $this->section;
    }
}


<?php

namespace HexaPrWire\Billing\Admin\Navigation;

final class SectionNavigation {
    public function __construct( private AdminNavigation $navigation ) {
    }

    public function render( AdminRoute $route, string $page_url ): void {
        $sections = $this->navigation->sections( $route->area() );
        if ( count( $sections ) < 2 ) {
            return;
        }
        ?>
        <nav class="hpr-billing-section-tabs" aria-label="<?php echo esc_attr( ( $this->navigation->areas()[ $route->area() ] ?? $route->area() ) . ' sections' ); ?>">
            <?php foreach ( $sections as $id => $label ) : ?>
                <a class="hpr-billing-section-tab<?php echo $route->section() === $id ? ' is-active' : ''; ?>" href="<?php echo esc_url( $this->navigation->section_url( $page_url, $route->area(), (string) $id ) ); ?>" <?php echo $route->section() === $id ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
            <?php endforeach; ?>
        </nav>
        <?php
    }
}


<?php

declare(strict_types=1);

$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Fields/AcfFields.php' );

$requirements = [
    "'label'    => 'Purchased At'",
    "'label'    => 'Purchased For'",
    "'label'    => 'Stripe Invoice ID'",
    "'_hpr_portal_amount_cents'",
    "'_hpr_portal_currency'",
    "'_hpr_portal_invoice_link'",
    "'billing.hexawebsystems.com' !== \$host",
    "'disabled' => 1",
    'Billing authentication is required.',
];

foreach ( $requirements as $requirement ) {
    if ( ! str_contains( $source, $requirement ) ) {
        fwrite( STDERR, "FAIL: Missing portal provenance contract {$requirement}.\n" );
        exit( 1 );
    }
}

if ( str_contains( $source, 'target="_self"' ) ) {
    fwrite( STDERR, "FAIL: Internal Billing links must open in a separate tab.\n" );
    exit( 1 );
}

echo "PASS: Portal provenance fields and internal Billing link restrictions are present.\n";

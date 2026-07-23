<?php

namespace HexaPrWire\Billing\Bootstrap;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

final class ModuleAdapter implements ModuleInterface {
    public function __construct( private object $module ) {
        if ( ! is_callable( [ $module, 'register' ] ) ) {
            throw new \InvalidArgumentException( 'Billing modules must expose register().' );
        }
    }

    public function register(): void {
        $this->module->register();
    }
}


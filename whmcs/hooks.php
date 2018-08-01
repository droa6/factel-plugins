<?php
/**
 * Modulo de conexion con el API de facturación electrónica FACTEL.
 * Modulo de conexiones con WHMCS
 * @copyright Copyright (c) Itros Soluciones
 */

use WHMCS\Database\Capsule;

require_once __DIR__ . '/factel_commons.php';

function getModuleConfig() {
    $_config=[
        "token" => FALSE
    ];
 
    foreach (Capsule::table('tbladdonmodules')->where('module', 'factel')->get() as $factel_config) {
        if($factel_config->setting==="token") {
            $_config["token"]=$factel_config->value;
        }
    }

    return $_config;
}

add_hook('ClientAreaPageViewInvoice', 1, 'hook_getFactelDetails');
add_hook('InvoiceCancelled', 1, 'setFacturaAnulada');
add_hook('InvoiceCreationPreEmail', 1, 'crearFacturaElectronica');
add_hook('InvoiceCreation', 1, 'crearFacturaElectronica');
add_hook('AdminHomeWidgets', 1, function() {
    return new FactelStatusWidget();
});

/**
 * Configuration FACTEL widget
 */
class FactelStatusWidget extends \WHMCS\Module\AbstractWidget {

    protected $title = 'Facturación Electrónica';
    protected $description = 'Facturación Electrónica';
    protected $weight = 150;
    protected $columns = 1;
    protected $cache = false;
    protected $cacheExpiry = 120;
    protected $requiredPermission = '';

    public function getData()
    {
        return array();
    }

    public function generateOutput($data)
    {
        foreach (Capsule::table('tbladdonmodules')->where('module', 'factel')->get() as $factel_config) {
            if($factel_config->setting==="token") {
                $token=$factel_config->value;
            }
            if($factel_config->setting==="ambiente") {
                $ambiente=$factel_config->value;
            }
        }

        if($token===null) {
            echo <<<NOCONFIG
            <div style="margin:10px;padding:10px;background-color:#ff4d4d;text-align:center;font-size:16px;color:#FFA;">FACTEL no se encuentra configurado.</div><div style="float:left;width:50%"><span style='color:red;'>Cualquier factura creada fallara el proceso de creacion de FACTURACION ELECTRONICA.</span></div><div style="float:right;width:50%"><a href='configaddonmods.php?activated=true'><input name="search" value="Configurar" class="btn btn-primary"></a></div>
NOCONFIG;
            return;
        }

        $countEnviadas = Capsule::table('mod_factel_historico')
                        ->where('estado', 'enviada')->count();

        $countValidas = Capsule::table('mod_factel_historico')
                        ->where('estado', 'Aceptada')->count();

        $countNoValidas = Capsule::table('mod_factel_historico')
                        ->where('estado', 'Rechazada')->count();
                        
        $countPendientes = Capsule::table('mod_factel_historico')
                        ->where('estado', 'Pendiente')->count();    
        
        $countAnuladas = Capsule::table('mod_factel_historico')
                        ->where('estado', 'like', '%nulada con NC%')->count(); 
                        
        echo <<<ITEM
            <div class="icon-stats"><div class="row">
                <div class="col-sm-6">
                <div class="item">
                    <div class="number">
                        <a href="addonmodules.php?module=factel">
                            <span class="color-orange">$countPendientes</span>
                            <span class="unit">Pendientes</span>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="item">
                    <div class="number">
                        <a href="addonmodules.php?module=factel">
                            <span class="color-green">$countValidas</span>
                            <span class="unit">Validas&nbsp;&nbsp</span>
                        </a>
                    </div>
                </div> 
            </div>
            <div class="col-sm-6">
                <div class="item">
                    <div class="number">
                        <a href="addonmodules.php?module=factel">
                            <span>$countNoValidas</span>
                            <span class="unit">Rechazadas</span>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="item">
                    <div class="number">
                        <a href="addonmodules.php?module=factel">
                            <span class="text-danger">$countAnuladas</span>
                            <span class="unit">Anuladas</span>
                        </a>
                    </div>
                </div>
            </div>
        </div></div>
ITEM;

    } 
}

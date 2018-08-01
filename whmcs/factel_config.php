<?php
 
/**
 * Configuración especifica del módulo para cada sistema.
 * @copyright Copyright (c) Itros Soluciones
 */

// El nombre del usuario de WHMCS con permisos para usar el API interno.
defined('FACTEL_WHMCS_ADMIN') OR define('FACTEL_WHMCS_ADMIN','usuario');

// Numero minimo de factura a partir de la cual hay que generar una factura electrónica
defined('FACTEL_OFFSET') OR define('FACTEL_OFFSET','0');

// URL base para generar los links del sitio 
defined('FACTEL_WEBSITE') OR define('FACTEL_WEBSITE','RUTA BASE DEL WHMCS');

// URL para conectar con el API
defined('FACTEL_API') OR define('FACTEL_API','RUTA DE CONEXION AL API DE FACTEL PARA FACTURACION ELECTRONICA');

// El campo identificacion es un campo adicional en WHMCS que debe crearse, este es el numero de campo personalizado
defined('WHMCS_IDENTIFICACION_NUM') OR define('WHMCS_IDENTIFICACION_NUM','1');

// URL base para la parte administrativa de WHMCS
defined('FACTEL_ADMIN_WEBSITE') OR define('FACTEL_ADMIN_WEBSITE',FACTEL_WEBSITE.'admin/');

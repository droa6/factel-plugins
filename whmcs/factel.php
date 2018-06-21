<?php

/**
 * Modulo de conexion con el API de facturacion electronica FACTEL.
 * Descripcion y funciones principales del modulo
 * @copyright Copyright (c) Itros Soluciones
 */

use WHMCS\Database\Capsule;

// NO ES NECESARIO MODIFICAR CONFIGURACION EN ESTE ARCHIVO /////

require_once __DIR__ . '/factel_commons.php';

function getInvoiceUser($_invoiceid) {
    $factura = Capsule::table('tblinvoices')
                        ->where('id', $_invoiceid)
                        ->get();

    // custom field fieldid for identificacion is 10 
    $identificacion = Capsule::table('tblcustomfieldsvalues')
                ->where('relid', $factura[0]->userid)
                ->where('fieldid', 10)
                ->get();

    if (isset($factura[0]->userid)) {
        return $factura[0]->userid;
    }
    return FALSE;
}

function factel_config() {
    $configarray = array(
    "name" => "Factel",
    "description" => "Este modulo permite la integracion con FACTEL, producto de Itros para facturacion electronica.",
    "version" => "1.5",
    "author" => "<a href='https://itros.net/factel'>Itros Soluciones</a>",
    "language" => "spanish",
    "fields" => array(
        "token" => array ("FriendlyName" => "Token de conexion", "Type" => "text", "Size" => "25",
                              "Description" => "Es el token para conectar con Factel", "Default" => "", ),
    ));
    return $configarray;
}

function factel_activate() {
    try {
        if (! Capsule::schema()->hasTable('mod_factel_historico')) {
            Capsule::schema()->create(
                'mod_factel_historico',
                function ($table) {
                    $table->increments('id_factel');
                    $table->integer('id_factura');
                    $table->date('fecha_enviada');
                    $table->string('estado');
                    $table->string('receptor');
                    $table->string('userid');
                    $table->mediumText('xmlData');
                    $table->timestamps();
                }
            );
        }
        return array('status'=>'success','description'=>'Listo, el modulo de Facturacion Electronica (FACTEL) se encuentra activado. Ahora necesita configurarlo con su cuenta.');
    } catch (\Exception $e) {
        return array('status'=>'error','description'=> "Unable to create mod_factel_historico: {$e->getMessage()}" );
    }

}

function factel_deactivate() {
    // LA DESACTIVACION NO BORRA LOS DATOS DE FACTEL POR SI SE NECESITA SOLO ACTUALIZAR LA VERSION
    try {
        return array('status'=>'success','description'=>'Listo, el modulo de Factura Electronica se encuentra desactivado.');
    } catch (\Exception $e) {
        return array('status'=>'error','description'=>'Ocurrio un error desactivando el modulo, por favor intente nuevamente.');
    }

}

function getInvoicePDF($_invoiceid) {
  if (!function_exists('pdfInvoice')) {
    require_once(__DIR__ . '/../../../includes/invoicefunctions.php');
  }
  $pdfdata = pdfInvoice($_invoiceid);
  $doc = base64_encode($pdfdata);
  $apiresults = array(
    "result" => "success",
    "pdf" => $doc,
    "message" => "Success Message"
  );

  return $apiresults ;
}

function showMessage($_xmlObj, $_id_factura, $_clave, $_consecutivo, $_comprobante, $_xmlData) {
    $_comprobante=str_replace("\n","",$_comprobante);
    $_comprobante=str_replace("'","\"",$_comprobante);

    if(strpos($_comprobante,"ESTADO=aceptado")!==FALSE) {
        echo '<div class="successbox" id="factel-message" style="display: none;"><strong><span class="title" id="factel-result-title"></span></strong><br><div id="factel-result-detail"></div></div>';
    }
    if(strpos($_comprobante,"ESTADO=rechazado")!==FALSE || $_xmlObj->error === TRUE) {
        echo '<div class="infobox" id="factel-message" style="display: none;"><strong><span class="title" id="factel-result-title"></span></strong><br><div id="factel-result-detail"></div></div>';
    }
    
    if ($_xmlObj->error !== TRUE ) {
        echo "<pre>Clave: [".$_clave."]\n";
        echo "Consecutivo: [".$_consecutivo."]</pre>\n";

        $_original = localAPI('GetInvoice', ['invoiceid' => $_id_factura], FACTEL_WHMCS_ADMIN);
        if (strcmp($_original['result'],"success")==0) {
            if (strcmp($_original['status'],"Cancelled")!=0) {
                if(strpos($_comprobante,"ESTADO=aceptado")!==FALSE) {
                    Capsule::table('mod_factel_historico')->where('id_factura',$_id_factura)->update(
                        [
                            'estado' => 'Valida, comprobante aceptado.',
                            'xmlData' => $_xmlData
                        ]
                    );
                    echo "<script>setResult('Valida, comprobante aceptado.');</script>";
                }elseif(strpos($_comprobante,"ESTADO=rechazado")!==FALSE) {
                    Capsule::table('mod_factel_historico')->where('id_factura',$_id_factura)->update(
                        [
                            'estado' => 'Enviada, comprobante rechazado.',
                            'xmlData' => $_xmlData
                        ]
                    );
                    echo "<script>setResult('Enviada, comprobante rechazado.','<pre>".$_comprobante."</pre>');</script>";
                }
            } else {
                Capsule::table('mod_factel_historico')->where('id_factura',$_id_factura)->update(
                    [
                        'estado' => 'Cancelada'
                    ]
                );
                echo "<script>setResult('La factura fue anulada con una nota de credito.');</script>";
            }
        } else {
            echo "<script>setResult('La factura fue anulada con una nota de credito.');</script>";
        }
    } else {
        Capsule::table('mod_factel_historico')->where('id_factura',$_id_factura)->update(
            [
                'estado' => 'Error, no se envio al API',
                'xmlData' => $_xmlData
            ]
        );
        echo "<script>setResult('Error, no se envio al API.',\"<pre>".$_xmlObj->detalle->message."</pre>\");</script>";
    }
}

function factel_output($vars) {

    $modulelink = $vars['modulelink'];
    $version = $vars['version'];
    $apiToken = $vars['token'];
    $LANG = $vars['_lang'];
    
    echo <<<SCRIPT
        <script>function setResult(title,detail){
            $("#factel-result-title").html(title);
            $("#factel-result-detail").html(detail);
            $("#factel-message").toggle(true);
            }</script>
SCRIPT;
    $id_factura = $_GET['factelid'];

    if (!empty($id_factura)) {

        $factelHistoriCount = Capsule::table('mod_factel_historico')->where('id_factura',$id_factura)->count();
        $factelHistorico = Capsule::table('mod_factel_historico')->where('id_factura',$id_factura)->get();

        $xmlData=FALSE;

        if($factelHistoriCount==1) {
            $_xmlData=$factelHistorico[0]->xmlData;
            if(!empty($_xmlData)) {
                if(strpos($_xmlData,'"status":true')!==FALSE) {
                    $xmlData = json_decode($_xmlData);
                }
            }
        }

        if (!empty($_GET['getclave'])) {
            if($xmlData->clave!==FALSE) {
                echo $xmlData->clave;
            } else {
                echo "...";
            }
            return;
        }
        elseif (!empty($_GET['getconsecutivo'])) {
            if($xmlData->consecutivo!==FALSE) {
                echo $xmlData->consecutivo;
            } else {
                echo "...";
            }
            return;
        }elseif (!empty($_GET['refreshcomprobante'])) {
            // recargar el comprobante desde Hacienda.
            $_cl=$xmlData->clave;
            if (strlen(trim($_GET['refreshcomprobante']))==50) {
                $_cl=$_GET['refreshcomprobante'];
            }
            // echo "XMLDATA: <pre>$_xmlData</pre>";
            echo "<h2>Obteniendo el comprobante actualizado de la factura # $id_factura, con la clave # $_cl</h2><br>";
            $_comprobanteData = getComprobanteFactel($_cl,$apiToken);
            $comprobanteObj=json_decode($_comprobanteData);
            if($_comprobanteData!==FALSE) {
                $xmlData->clave = $_cl;
                $xmlData->consecutivo = $comprobanteObj->consecutivo;
                $xmlData->comprobante = $comprobanteObj->comprobante;
                $xmlData->xml=$comprobanteObj->xml;
                // echo "XMLDATA UPDATE: <pre>".json_encode($xmlData)."</pre>";
                showMessage($xmlData, $id_factura, $xmlData->clave, $xmlData->consecutivo, $xmlData->comprobante, json_encode($xmlData));
            }
            return;
        }elseif (!empty($_GET['anularfactura'])) {
            // eliminar la factura con una nota de credito
            $_cl=$xmlData->clave;
            $_cons=$xmlData->consecutivo;

            if (strlen(trim($_GET['anularfactura']))==50) {
                $_cl=$_GET['anularfactura'];
            }

            echo "<h2>Anulando la factura# $_cons, con la clave # $_cl</h2><br>";
            $_anularFactura = setFacturaAnulada(["invoiceid" => $id_factura]);

            if($_anularFactura!==FALSE) {
                if ($_anularFactura->status == TRUE) {
                    echo "<pre>Se anulo la factura con NC#".$_anularFactura->clave."</pre>";
                } else {
                    echo "<pre>No se puede anular una factura que no ha sido aceptada</pre>";
                    echo "Detalle:<br/><pre>".json_encode($_anularFactura->detalle)."</pre>";
                }
            } else {
                echo "<pre>No se pudo anular</pre>";
            }
            
            return;
        }

        if (!empty($_GET['retry'])) {
            $xmlData=FALSE;
            echo "<h2>Intentando nuevamente</h2><br>";
        }
 
        if($xmlData===FALSE) {
            // no se ha firmado...
            echo "<h2>Firmando factura # $id_factura</h2><br>";
            $_xmlData = sendFacturaFactel($id_factura,$apiToken);
            // DEBUG echo "<pre>$_xmlData</pre>";
            $xmlData=json_decode($_xmlData);
        } else {
            echo "<h2>Respuesta del firmado para la factura # $id_factura</h2><br>";
        }
        showMessage($xmlData, $id_factura, $xmlData->clave, $xmlData->consecutivo, $xmlData->comprobante, $_xmlData);
        return;
    }
    
    // No se pidio una factur en especial, muestra todas las facturas disponibles en el sistema.

    echo <<<HEADER
<table id="sortabletbl1" class="datatable" width="100%" cellspacing="1" cellpadding="3" border="0">
<tbody><tr><th>Anular</th><th>Factura #</th><th>Receptor</th><th>Fecha Enviado</th><th>Estado</th><th>Respuesta</th><th>XML Firmado</th><th>Comprobante</th><th>PDF</th></tr>
HEADER;

    foreach (Capsule::table('mod_factel_historico')->orderBy('id_factura', 'desc')->get() as $factel_item) {
        $id=$factel_item->id_factura;
        if(isset($factel_item->id_factura)) {

            $estado=$factel_item->estado;
            $estado_style="";
            $xmlRetry="";
            $xmlRetryLink="";

            if ( strpos($estado, "ancela") === FALSE ) {
                if(isset($factel_item->xmlData)) {
                    $xmlData = json_decode($factel_item->xmlData);
                    if(strpos($xmlData->comprobante,"ESTADO=aceptado")!=FALSE) {
                        $estado="Aceptada";
                        $comprobanteicon="<i style=\"color:green;\" class=\"fa fa-check\"></i>";
                    }

                    if(strpos($xmlData->comprobante,"ESTADO=recibido")!=FALSE || strpos($xmlData->comprobante,"ESTADO=procesando")!=FALSE || empty($xmlData->comprobante)) {
                        $extraTemplateVariables['factel_pendiente']=1;
                        $estado_style="style='color:orange'";
                        $estado="Pendiente";
                        $comprobanteicon="<i style=\"color:orange;\" class=\"fa fa-clock-o\"></i>";
                        $xmlRetry="&nbsp;|&nbsp;Reintentar";
                    }

                    if(strpos($xmlData->comprobante,"ESTADO=rechazado")!=FALSE) {
                        $extraTemplateVariables['factel_rechazada']=1;
                        $estado_style="style='color:red'";
                        $estado="Rechazada";
                        $comprobanteicon="<i style=\"color:red;\" class=\"fa fa-times\"></i>";
                        $xmlRetry="";
                    }
                } else {
                    $estado="Error, XML vacio";
                } 
                // UPDATE INVOICE STATUS
                Capsule::table('mod_factel_historico')->where('id_factura',$factel_item->id_factura)->update(
                    [
                        'estado' => $estado
                    ]
                );
                $anularicon="<a onclick=\"return confirm('Desea anular la factura #".$factel_item->id_factura." ?')\" href=\"$modulelink&factelid=$id&anularfactura=1\"><i class=\"fa fa-trash\"></i></a>";
            } else {
                $nctooltip=substr($estado,17);
                $estado="Cancelada con NC";
                $anularicon="<a title=\"$nctooltip\" href=\"#\"><i style=\"color:gray;\" class=\"fa fa-trash\"></i>&nbsp;<i style=\"color:green;\" class=\"fa fa-check\"></i></a>";
                $estado_style="style='color:red'";
                $comprobanteicon="<i style=\"color:green;\" class=\"fa fa-check\"></i>";
            }

            $fecha=$factel_item->fecha_enviada;
            $receptor=empty($factel_item->receptor)?"Sin identificacion":$factel_item->receptor;
            $receptor_style=empty($factel_item->receptor)?"style='color:orange'":'';
            $userid=$factel_item->userid;

            // UPDATE ALL INVOICE OFFSETS
            if (TRUE) {
                $_original = localAPI('GetInvoice', ['invoiceid' => $factel_item->id_factura], FACTEL_WHMCS_ADMIN);
                if (strcmp($_original['result'],"success")==0) {
                    if(strlen($_original["invoicenum"])==20 && !empty($_original["invoicenum"])) {
                        $_numeracion="<i class=\"fa fa-check\"></i>&nbsp;".$_original["invoicenum"];
                    } else {
                        $_newInvoiceId=getConsecutiveFormat($factel_item->id_factura);
                        if($_newInvoiceId!=FALSE) {
                            $_modified = localAPI("UpdateInvoice",['invoiceid' => $factel_item->id_factura, "invoicenum" => trim("".$_newInvoiceId) ], FACTEL_WHMCS_ADMIN);
                            if (strcmp($_modified['result'],"success")==0) {
                                $_numeracion="<i class=\"fa fa-check\"></i>&nbsp;".$_original["invoicenum"];
                            } else {
                                $_numeracion="<i class=\"fa fa-times-circle \"></i>&nbsp;".$_original["invoicenum"];
                            }
                        } else {
                            $_numeracion="<i class=\"fa fa-check\"></i>&nbsp;".$factel_item->id_factura;
                        }
                    }
                }
            }
        } else {
            $estado_style="";
            $estado = "Sin registro de factura electronica.";
        }
        
        $fadownload="<i class=\"fa fa-download\"></i>";
        $fafilepdf="<i class=\"fa fa-file-pdf-o\"></i>";
        $fafilexml="<i class=\"fa fa-file-excel-o\"></i>";
        $farefresh="<i class=\"fa fa-refresh\"></i>";

        $adminurl = FACTEL_WEBSITE."index.php";
        echo <<<TABLA
<tr><td style='text-align:center;'>$anularicon</td><td><a href='invoices.php?action=edit&id=$id'>$_numeracion</a></td><td><a $receptor_style href="clientsprofile.php?userid=$userid">$receptor</a></td><td>$fecha</td><td $estado_style>$estado</td><td $estado_style><a href='$modulelink&factelid=$id'>Ver respuesta<a><a href='$modulelink&factelid=$id&retry=1' style='color:red'>$xmlRetry<a></td><td style='text-align:center;'><a href='$adminurl?m=factel&clave=$id&xml=1'>$fafilexml<a></td><td style='text-align:center;'>$comprobanteicon&nbsp;&nbsp;<a href='$adminurl?m=factel&clave=$id&comprobante=1'>$fadownload<a>&nbsp;&nbsp;&nbsp;<a href='$modulelink&factelid=$id&refreshcomprobante=1'>$farefresh<a></td><td style='text-align:center;'><a href='$adminurl?m=factel&clave=$id&pdf=1'>$fafilepdf<a></td></tr>
TABLA;

    }

    echo <<<TABLA
<tr><td>Ultima linea.</td><td></td><td></td><td></td><td></td><td></td></tr>
TABLA;
    
    echo "</tbody></table>";
}

function factel_clientarea($vars) {
 
    $modulelink = $vars['modulelink'];
    $version = $vars['version']; 
    $apiToken = $vars['token'];
    $LANG = $vars['_lang'];

    $vars = array();

    if (!empty($_POST['clave'])) {
        $_invoiceid = filter_var ( $_POST['clave'], FILTER_SANITIZE_NUMBER_INT);
        if(strlen($_invoiceid)<20) {
            $_invoiceid += (int)FACTEL_OFFSET;
        }
    }elseif (!empty($_GET['clave'])) {
        $_invoiceid = filter_var ( $_GET['clave'], FILTER_SANITIZE_NUMBER_INT);
    } else {
        $vars['invalidInvoiceIdRequested']=TRUE;
        $_invoiceid=FALSE;
    }

    if (!empty($_GET['pdf'])) {
        $_downloadpdf = TRUE;
    }

    if (!empty($_GET['xml'])) {
        $_downloadxml = TRUE;
    }

    if (!empty($_GET['comprobante'])) {
        $_downloadcomprobante = TRUE;
    }

    if (!empty($_invoiceid)) {
        if(strlen($_invoiceid)==20) {
            // consecutivo   
            $_invoiceid=(int)substr($_invoiceid,10);
            $_invoiceid += (int)FACTEL_OFFSET;
        }

        if(strlen($_invoiceid)==50) {
            // clave    
            $_invoiceid=(int)substr($_invoiceid,31,10);
            $_invoiceid += (int)FACTEL_OFFSET;
        }

        $_details = hook_getFactelDetails(['invoiceid'=>$_invoiceid]);

        if ( $_details!==FALSE && $_details['is_factel'] ) {
            createQrCode($_invoiceid, $_details["factel_consecutivo"],  $_details["factel_clave"]);

            if ($_downloadpdf==TRUE && $_details['factel_aceptada']) {
                $_invoicepdf = getInvoicePDF($_invoiceid);
                $_file="downloads/factura_pdf_".$_details["factel_consecutivo"].".pdf";
                if(file_exists($_file)) {
                    unlink($_file);
                }
                file_put_contents($_file,base64_decode($_invoicepdf["pdf"]));
                downloadFile($_file);
            }

            if ($_downloadxml==TRUE && $_details['factel_aceptada']) {
                $_file="downloads/factura_xml_".$_details["factel_consecutivo"].".xml";
                if(file_exists($_file)) {
                    unlink($_file);
                }
                file_put_contents($_file,$_details["factel_xml"]);
                downloadFile($_file);
            }

            if ($_downloadcomprobante==TRUE) {
                $_file="downloads/comprobante_factura_".$_details["factel_consecutivo"].".txt";
                if(file_exists($_file)) {
                    unlink($_file);
                }
                file_put_contents($_file,$_details["factel_comprobante"]);
                downloadFile($_file);
            }

            $command = 'GetInvoice';
            $postData = array(
                'invoiceid' => $_invoiceid,
            );
            $adminUsername = FACTEL_WHMCS_ADMIN;

            $results = localAPI($command, $postData, $adminUsername);

            if (strcmp($results['result'],"success")==0) {
                $vars["factel_consecutivo"] = $_details["factel_consecutivo"];
                $vars["factel_clave"] = $_details["factel_clave"];
                $vars["invoiceid"] = $_invoiceid;
                $_receptorDetails = getReceptorDetails($results['userid']);
                $vars["status"] = $results["status"];
                $vars["clientsdetails_companyname"] = $_receptorDetails["companyname"];
                $vars["clientsdetails_firstname"] = $_receptorDetails["firstname"];
                $vars["clientsdetails_lastname"] = $_receptorDetails["lastname"];
                $vars["clientsdetails_address1"] = $_receptorDetails["address1"];
                $vars["clientsdetails_address2"] = $_receptorDetails["address2"];
                $vars["datedue"] = $results['duedate'];
                $vars["date"] = $results['date'];
                $vars["invoiceitems"] = $results["items"]["item"];
                $vars["subtotal"] = $results['subtotal'];
                $vars["tax"] = $results['tax'];
                $vars["total"] = $results['total'];
                $vars["tax2"] = $results['tax2'];
                $vars["credit"] = $results['credit'];
                $vars["total"] = $results['total'];
                $vars['factel_aceptada'] = $_details['factel_aceptada'];
                $vars['factel_rechazada'] = $_details['factel_rechazada'];
                $vars['factel_pendiente'] = $_details['factel_pendiente'];
            } else {
                $vars['invalidInvoiceIdRequested']=TRUE;
            }
        } else {
            $vars['invalidInvoiceIdRequested']=TRUE;
        }
    } else {
        $vars['invalidInvoiceIdRequested']=TRUE;
    }

    $data = array(
        'pagetitle' => 'Facturacion Electrónica',
        'breadcrumb' => array('index.php?m=factel'=>'Ver mi factura electrónica'),
        'templatefile' => 'factel',
        'requirelogin' => false,
        'forcessl' => true,
        'vars' => $vars
    );
 
    return $data;
}
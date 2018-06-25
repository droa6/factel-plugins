<?php
 
/**
 * Modulo de conexion con el API de facturacion electronica FACTEL.
 * Funciones comunes del modulo
 * @copyright Copyright (c) Itros Soluciones
 */

use WHMCS\Database\Capsule;
 
require_once __DIR__ . '/factel_config.php';
 
if(!function_exists("createQrCode")) {
function createQrCode($_invoiceid,$_consecutivo,$_clave) {
    $size = '150x150';
    $QR = imagecreatefrompng('https://chart.googleapis.com/chart?cht=qr&chld=H|1&chs='.$size.'&chl='.urlencode(FACTEL_WEBSITE."index.php?m=factel&clave=$_invoiceid"));
    if(file_exists("assets/img/qr_factura_$_invoiceid.png")) {
        unlink("assets/img/qr_factura_$_invoiceid.png");
    } 
    imagepng($QR,"assets/img/qr_factura_$_invoiceid.png");
    if(file_exists("downloads/info_factura_$_invoiceid.txt")) {
        unlink("downloads/info_factura_$_invoiceid.txt");
    }
    file_put_contents("downloads/info_factura_$_invoiceid.txt","$_consecutivo,$_clave");
}
}  

if(!function_exists("hook_getFactelDetails")) {
function hook_getFactelDetails($vars) {
    $extraTemplateVariables = array();
    $factelHistorico = Capsule::table('mod_factel_historico')->where('id_factura',$vars['invoiceid'])->get();
    if(isset($factelHistorico[0]->xmlData)) {
        $extraTemplateVariables['is_factel']=TRUE;
        $_xmlData=$factelHistorico[0]->xmlData;
        $xmlData = json_decode($_xmlData);
        createQrCode($vars['invoiceid'],$xmlData->consecutivo, $xmlData->clave);

        $extraTemplateVariables['factel_clave']=$xmlData->clave;
        $extraTemplateVariables['factel_consecutivo']=$xmlData->consecutivo;
        $extraTemplateVariables['factel_comprobante']=$xmlData->comprobante;
        $extraTemplateVariables['factel_xml']=$xmlData->xml;

        if(strpos($xmlData->comprobante,"recepcionEstado=aceptado")!=FALSE) {
            $extraTemplateVariables['factel_aceptada']=1;
        }

        if(strpos($xmlData->comprobante,"recepcionEstado=procesando")!=FALSE) {
            $extraTemplateVariables['factel_pendiente']=1;
        }
        if(empty($xmlData->comprobante)) {
            $extraTemplateVariables['factel_pendiente']=1;
        }

        if(strpos($xmlData->comprobante,"recepcionEstado=rechazado")!=FALSE) {
            $extraTemplateVariables['factel_rechazada']=1;
        }

        return $extraTemplateVariables;
    } else {
        $extraTemplateVariables['is_factel']=FALSE;
    }
    return FALSE;
}
}

function downloadFile($_file_path) { //, $_downloadname) {

    //- turn off compression on the server
    //        @apache_setenv('no-gzip', 1);
    //        @ini_set('zlib.output_compression', 'Off');
        
    // sanitize the file request, keep just the name and extension
    // also, replaces the file location with a preset one ('./myfiles/' in this example)
    $file_path  = $_file_path;
    $path_parts = pathinfo($file_path);
    $file_name  = $path_parts['basename'];
    $file_ext   = $path_parts['extension'];
    $file_path  = "downloads/" . $file_name;
    // allow a file to be streamed instead of sent as an attachment
    $is_attachment = isset($_REQUEST['stream']) ? false : true;

    // make sure the file exists
    if (is_file($file_path)) {
        $file_size  = filesize($file_path);
        $file = @fopen($file_path,"rb");
        if ($file)
        {
            // set the headers, prevent caching
            header("Pragma: public");
            header("Expires: -1");
            header("Cache-Control: public, must-revalidate, post-check=0, pre-check=0");
            header("Content-Disposition: attachment; filename=\"$file_name\"");
     
            // set appropriate headers for attachment or streamed file
            if ($is_attachment)
                    header("Content-Disposition: attachment; filename=\"$file_name\"");
            else
                    header('Content-Disposition: inline;');
     
            // set the mime type based on extension, add yours if needed.
            $ctype_default = "application/octet-stream";
            $content_types = array(
                    "exe" => "application/octet-stream",
                    "zip" => "application/zip",
                    "mp3" => "audio/mpeg",
                    "mpg" => "video/mpeg",
                    "avi" => "video/x-msvideo",
            );
            $ctype = isset($content_types[$file_ext]) ? $content_types[$file_ext] : $ctype_default;
            header("Content-Type: " . $ctype);
     
            //check if http_range is sent by browser (or download manager)
            if(isset($_SERVER['HTTP_RANGE']))
            {
                list($size_unit, $range_orig) = explode('=', $_SERVER['HTTP_RANGE'], 2);
                if ($size_unit == 'bytes')
                {
                    //multiple ranges could be specified at the same time, but for simplicity only serve the first range
                    //http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
                    list($range, $extra_ranges) = explode(',', $range_orig, 2);
                }
                else
                {
                    $range = '';
                    header('HTTP/1.1 416 Requested Range Not Satisfiable');
                    exit;
                }
            }
            else
            {
                $range = '';
            }
     
            //figure out download piece from range (if set)
            list($seek_start, $seek_end) = explode('-', $range, 2);
     
            //set start and end based on range (if set), else set defaults
            //also check for invalid ranges.
            $seek_end   = (empty($seek_end)) ? ($file_size - 1) : min(abs(intval($seek_end)),($file_size - 1));
            $seek_start = (empty($seek_start) || $seek_end < abs(intval($seek_start))) ? 0 : max(abs(intval($seek_start)),0);
     
            //Only send partial content header if downloading a piece of the file (IE workaround)
            if ($seek_start > 0 || $seek_end < ($file_size - 1))
            {
                header('HTTP/1.1 206 Partial Content');
                header('Content-Range: bytes '.$seek_start.'-'.$seek_end.'/'.$file_size);
                header('Content-Length: '.($seek_end - $seek_start + 1));
            }
            else
              header("Content-Length: $file_size");
     
            header('Accept-Ranges: bytes');
     
            set_time_limit(0);
            fseek($file, $seek_start);
     
            while(!feof($file)) 
            {
                print(@fread($file, 1024*8));
                ob_flush();
                flush();
                if (connection_status()!=0) 
                {
                    @fclose($file);
                    exit;
                }			
            }
     
            // file save was a success
            @fclose($file);
            exit;
        }
        else 
        {
            // file couldn't be opened
            header("HTTP/1.0 500 Internal Server Error");
            exit;
        }
    }
    else
    {
        // file does not exist
        header("HTTP/1.0 404 Not Found");
        exit;
    }
}

if(!function_exists("downloadXML")) {
    function downloadXML($_xmldata, $_consecutivo) {
        header("HTTP/1.0 404 Not Found");
        file_put_contents("downloads/".$_consecutivo.".xml",$_xmldata);
        downloadFile($_consecutivo.".xml");
    }
}

if(!function_exists("objectPrinter")) {
    function objectPrinter($obj, $level="") {
        $output="";
        foreach ($obj as $key => $value) {
            if (is_object($value)) {
                $output = $output."$level$key:\n";
                $output = $output.objectPrinter($value,$level."   ");
            } elseif (is_array($value)) {
                $output = $output."$level$key:\n";
                foreach ($value as $arrKey => $arrValue) {
                    $output = $output.objectPrinter($value,$level."   ");
                }
            } else {
                $output = $output."$level$key: $value\n";
            }
        }
        return $output;
    }
}

if(!function_exists("getComprobanteFactel")) {
    function getComprobanteFactel($clave_factura,$apiToken) {
        $_xmlData["clave"]=$clave_factura;
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
        CURLOPT_URL => FACTEL_API."comprobantes/",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($_xmlData),
        CURLOPT_HTTPHEADER => array(
                "content-type: application/json",
                "factel-api-key: ".$apiToken
            ), 
        ));
    
        $response = curl_exec($curl);
        $err = curl_error($curl);
    
        curl_close($curl);
    
        if ($err) {
            return json_encode(["error" => TRUE, "message" => "Error realizando el envio de datos al API de facturacion"]);
        } else {
            $j_response = json_decode(trim($response));
            if($j_response->status!==FALSE) {
                return trim($response);
            }
            return json_encode(["error" => TRUE, "message" => "Error en el proceso del API de facturacion", "detalle" => $j_response]);            
        }
    }
}

if(!function_exists("anularFacturaFactel")) {
    function anularFacturaFactel($consecutivoNota, $referenciaFactura, $apiToken) {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => FACTEL_API."facturas/",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "DELETE",
            CURLOPT_POSTFIELDS => "{\n\t\"consecutivo\":\"$consecutivoNota\",\n\t\"referencia\":\"$referenciaFactura\",\n\t\"firmar\":true\n}\n",
            CURLOPT_HTTPHEADER => array(
                "content-type: application/json",
                "factel-api-key: $apiToken"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return json_encode(["error" => TRUE, "message" => "Error realizando el envio de datos al API de facturacion"]);
        } else {
            $j_response = json_decode(trim($response));
            if($j_response->status!==FALSE) {
                return trim($response);
            }
            return json_encode(["error" => TRUE, "message" => "Error en el proceso del API de facturacion", "detalle" => $j_response]);            
        }
    }
}

if(!function_exists("sendFacturaFactel")) {
    function sendFacturaFactel($id_factura,$apiToken) {
        $command = 'GetInvoice';
        $postData = array(
            'invoiceid' => $id_factura,
        );
        $adminUsername = FACTEL_WHMCS_ADMIN;

        $results = localAPI($command, $postData, $adminUsername);
    
        if (strcmp($results['result'],"success")==0) {

            $_idReceptor = getReceptorId($id_factura);
            Capsule::table('mod_factel_historico')->where('id_factura',$id_factura)->update(
                [
                    'receptor' => $_idReceptor
                ]
            );

            $_receptorDetails = getReceptorDetails($results['userid']);
 
            if ($_receptorDetails==FALSE) {
                return json_encode(["error" => TRUE, "message" => "Error buscando los datos del cliente en WHMCS"]);
            }
    
            $_receptorData = [
                "cedula" => $_idReceptor,
                "nombre" => $_receptorDetails["fullname"],
                "codigo_postal" => $_receptorDetails["postcode"],
                "email" => $_receptorDetails["email"]
            ];

            if (strcmp(trim(strtolower($_receptorDetails["countryname"])),"costa rica")!=0) {
                // extranjero
                $_receptorData["extranjero"]="true";
            }

            $_xmlData = Array(
                "receptor" => $_receptorData,
                "consecutivo" => trim("".(((int)$results["invoiceid"])-FACTEL_OFFSET))
            );
    
            $_items = Array();
    
            foreach ($results["items"]["item"]  as $key => $value) {
                $_items[1+$key] = Array(
                    "cantidad" => "1",
                    "unidad" => "Otros",
                    "detalle" => $value["description"],
                    "preciounitario" => $value["amount"]
                );
            }
    
            $_xmlData["lineas"] = $_items;
        } else {
            $_xmlData = Array("error"=>TRUE);
        }

        $_xmlData["codigomoneda"]="CRC"; // colones por defecto
        $_xmlData["tipodecambio"]="01"; // tipo de cambio
        $_xmlData["mediopago"]="01"; // medio de pago CONTADO  
        $_xmlData["condicionventa"]="01"; // venta de CONTADO  
        $_xmlData["firmar"] = "true"; // requiere la firma inmediata

        if (!$_xmlData['error']) {
            $curl = curl_init();
        
            curl_setopt_array($curl, array(
            CURLOPT_URL => FACTEL_API."facturas/",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($_xmlData),
            CURLOPT_HTTPHEADER => array(
                    "content-type: application/json",
                    "factel-api-key: ".$apiToken
                ),
            ));
        
            $response = curl_exec($curl);
            $err = curl_error($curl);
        
            curl_close($curl);
        
            if ($err) {
                return json_encode(["error" => TRUE, "message" => "Error realizando el envio de datos al API de facturacion"]);
            } else {
                $j_response = json_decode(trim($response));
                if($j_response->status!==FALSE) {
                    return trim($response);
                }
                return json_encode(["error" => TRUE, "message" => "Error en el proceso del API de facturacion", "detalle" => json_decode(trim($response))]);            
            }
    
        } else {
            return json_encode(["error" => TRUE, "message" => "Error consultando la informacion de la factura en el sistema local"]);
        }
    }
}

if(!function_exists("getReceptorDetails")) {
    function getReceptorDetails($_clientid) {
        if($_clientid==FALSE) {
            return FALSE;
        }
        $command = 'GetClientsDetails';
        $postData = array(
            'clientid' => $_clientid,
            'stats' => FALSE,
        );
        $adminUsername = FACTEL_WHMCS_ADMIN;
        
        $results = localAPI($command, $postData, $adminUsername);
    
        if (strcmp($results['result'],"success")==0) {
            return $results['client'];
        }
        return FALSE;
    }
}
 
if(!function_exists("getReceptorId")) {
    function getReceptorId($_invoiceid) {
        $factura = Capsule::table('tblinvoices')
                            ->where('id', $_invoiceid)
                            ->get();

        // custom field fieldid for identificacion is 10 
        $identificacion = Capsule::table('tblcustomfieldsvalues')
                    ->where('relid', $factura[0]->userid)
                    ->where('fieldid', (int)WHMCS_IDENTIFICACION_NUM)
                    ->get();

        if (isset($identificacion[0]->value)) {
            $_receptorId = trim($identificacion[0]->value);
            $_receptorId = str_replace("-","",$_receptorId);
            return $_receptorId;
        }
        return FALSE;
    }
}

if(!function_exists("getConsecutiveFormat")) {
    function getConsecutiveFormat($_idFactura) {
        $_consecutive=(((int)$_idFactura)-FACTEL_OFFSET);
        if($_consecutive>0) {
            return "0010000101".substr("0000000000".$_consecutive, -10);
        } else {
            return "0010000101".substr("0000000000".$_idFactura, -10);
        }
    }
}

if(!function_exists("setFacturaAnulada")) {
    function setFacturaAnulada($vars) {
        $invoiceid = $vars['invoiceid'];
        $facturaCount = Capsule::table('mod_factel_historico')->where('id_factura', $invoiceid)->count();
        if ($facturaCount==1) {
            // hay una factura electronica disponible para anular
            $facturaData = Capsule::table('mod_factel_historico')->where('id_factura', $invoiceid)->get();
            $factuData = $facturaData[0];
            if ( strpos($factuData->estado, "Anulada con NC") !== FALSE ) {
                // factura ya fue anulada
                $xmlObj = json_decode($factuData->xmlData);
                return [
                    "status" => false,
                    "clave" => $xmlObj->clave,
                    "estado" => $factuData->estado
                ];
            } elseif ( strcmp(strtolower($factuData->estado), "aceptada") !== FALSE ) {
                // se puede eliminar la factura, tiene estado aceptada
                $xmlObj = json_decode($factuData->xmlData);
                $notasCount = Capsule::table('mod_factel_historico')
                                ->where('estado', 'like', '%nulada con NC%')
                                ->count();
                $notasCount=($notasCount+1)."";
                
                $config = getModuleConfig();
                
                $ar = json_decode(anularFacturaFactel($notasCount,$xmlObj->consecutivo,$config["token"]));

                if ( $ar->status == TRUE ) {
                    // factura anulada en el API
                    Capsule::table('mod_factel_historico')->where('id_factura',$invoiceid)->update(
                        [
                            'estado' => 'Anulada con NC#'.$ar->clave
                        ]
                    );
                    $_original = localAPI('GetInvoice', ['invoiceid' => $invoiceid], FACTEL_WHMCS_ADMIN);
                    if (strcmp($_original['result'],"success")==0) {
                        localAPI("UpdateInvoice",[
                            "invoiceid" => $invoiceid,
                            "status" => "Cancelled",
                            "notes" => "Anulada con NC#".$ar->clave
                        ], FACTEL_WHMCS_ADMIN);
                    }
                } else {
                    Capsule::table('mod_factel_historico')->where('id_factura',$invoiceid)->update(
                        [
                            'estado' => 'Error al cancelar'
                        ]
                    );
                }
                return $ar;
            } else {
                // no se puede eliminar, la factura no fue aceptada
                return FALSE;
            }
        }
    }
}

if(!function_exists("getFacturaDetails")) {
    function getFacturaDetails($vars) {
        $invoiceid = $vars['invoiceid'];
        $facturaCount = Capsule::table('mod_factel_historico')->where('id_factura', $invoiceid)->count();
    
        if ($facturaCount==0) {

            $facturaCount = Capsule::table('tblinvoices')->where('id', $invoiceid)->count();
    
            if($facturaCount==1) {
                $factura = Capsule::table('tblinvoices')
                            ->where('id', $invoiceid)
                            ->get();
    
                $identificacion = Capsule::table('tblcustomfieldsvalues')
                            ->where('relid', $factura[0]->userid)
                            ->where('fieldid', 10)
                            ->get();

                if($identificacion[0]->value==NULL) {
                    $receptorIdentificacion="";
                } else {
                    $receptorIdentificacion=$identificacion[0]->value;
                }
    
                Capsule::table('mod_factel_historico')->insert(
                        [
                            'id_factura' => $invoiceid,
                            'fecha_enviada' => $factura[0]->date,
                            'receptor' => $receptorIdentificacion,
                            'estado' => 'Pendiente: firmando y enviando',
                            'userid' => $factura[0]->userid,
                            'xmlData' => ""
                        ]
                );
                 
                $config = getModuleConfig();
    
                $_xmldata = sendFacturaFactel($invoiceid, $config["token"]);
                $xmlobj=json_decode($_xmldata);

                // set the invoice num to long invoice format
                $adminuser = FACTEL_WHMCS_ADMIN;
                $command = "UpdateInvoice";
                $values = [
                    "invoiceid" => $invoiceid,
                    "invoicenum" => getConsecutiveFormat($invoiceid)
                ];
                localAPI($command, $values, $adminuser);

                if($xmlobj->error!==TRUE) {
                    if(strpos($xmlobj->comprobante,"recepcionEstado=rechazado")==FALSE) {
                        Capsule::table('mod_factel_historico')->where('id_factura',$invoiceid)->update(
                            [
                                'estado' => 'Valida, comprobante aceptado.',
                                'xmlData' => $_xmldata
                            ]
                        );
                    } else {
                        Capsule::table('mod_factel_historico')->where('id_factura',$invoiceid)->update(
                            [
                                'estado' => 'Enviada, comprobante rechazado.',
                                'xmlData' => $_xmldata
                            ]
                        );
                    }
                    
                } else {
                    Capsule::table('mod_factel_historico')->where('id_factura',$invoiceid)->update(
                        [
                            'estado' => 'Pendiente: error al procesar en el API.',
                            'xmlData' => $_xmldata
                        ]
                    );
                }
            } else {
                Capsule::table('mod_factel_historico')->insert(
                    [
                        'id_factura' => $invoiceid,
                        'fecha_enviada' => 'noinfo',
                        'receptor' => 'noinfo',
                        'estado' => 'Pendiente: sin datos.'
                    ]
                );
            }
    
        }
    }
}

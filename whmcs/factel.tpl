<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="{$charset}" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$companyname} - {$pagetitle}</title>

    <link href="{$WEB_ROOT}/templates/{$template}/css/all.min.css" rel="stylesheet">
    <link href="{$WEB_ROOT}/templates/{$template}/css/invoice.css" rel="stylesheet">
 
</head>
<body>

    <div class="container-fluid invoice-container">

        {if $invalidInvoiceIdRequested}

            <form method="post" action="/factu/index.php?m=factel" enctype="multipart/form-data" role="form">
            <h1>Comprobar factura electrónica</h1>
            <div class="row">
            <p class="text-center">
            <div class="form-group col-sm-10">
            
            <label for="inputName">Digite la clave o número de factura electrónica</label>
            <input name="clave" id="clave" value="" class="form-control" type="text" size="50">
            
            </div>
            </p>
            </div>
            <p class="text-center">
            <input id="factel-submit" value="Comprobar" class="btn btn-primary" type="submit">
            </p>
            </form>

        {else} 

            <div class="row">
                <div class="col-sm-7">
 
    <div style="display:none"><pre>{$factel_comprobante}</pre></div>
                    {if $logo}
                        <p><img src="{$logo}" title="{$companyname}" /></p>
                    {else}
                        <h2>{$companyname}</h2>
                    {/if}

                    {if $factel_aceptada}
                        <h3 style="font-size:18px;">Vista previa de factura # {$factel_consecutivo}</h3>
                    {/if}

                    {if $factel_pendiente}
                        <h3 style="font-size:18px;color:orange;">Factura electrónica en proceso de validación.</h3>
                        <h4>Por favor revise esta página en unos minutos o cuando nuestro personal le comunique que la validación fue completada.</h4>
                    {/if}

                    {if $factel_rechazada}
                        <h3 style="font-size:18px;color:red;">Factura electrónica no válida</h3>
                        <h4>El comprobante de esta factura electrónica fue rechazado.</h4>
                    {/if}

                </div>
                <div class="col-sm-5 text-center">

                    <div class="invoice-status">
                        {if $status eq "Draft"}
                            <span class="draft">{$LANG.invoicesdraft}</span>
                        {elseif $status eq "Unpaid"}
                            <span class="unpaid">{$LANG.invoicesunpaid}</span>
                        {elseif $status eq "Paid"}
                            <span class="paid">{$LANG.invoicespaid}</span>
                        {elseif $status eq "Refunded"}
                            <span class="refunded">{$LANG.invoicesrefunded}</span>
                        {elseif $status eq "Cancelled"}
                            <span class="cancelled">{$LANG.invoicescancelled}</span>
                        {elseif $status eq "Collections"}
                            <span class="collections">{$LANG.invoicescollections}</span>
                        {/if}
                    </div>

                    {if $status eq "Unpaid" || $status eq "Draft"}
                        <div class="small-text">
                            {$LANG.invoicesdatedue}: {$datedue}
                        </div>
                        <div class="payment-btn-container" align="center">
                            {$paymentbutton}
                        </div>
                    {/if}

                </div>
            </div>
            {if $factel_aceptada}
                    <p>Por favor proceda a descargar la factura en formato XML y PDF para su respectivo trámite y respaldo, usando los botones.</p>
                    <br>
                    <div class="pull-right btn-group btn-group-sm hidden-print">
                    <a href="index.php?m=factel&clave={$invoiceid}&comprobante=1" class="btn btn-default"><i class="fa fa-check"></i> Ver comprobante</a>
                    <a href="index.php?m=factel&clave={$invoiceid}&xml=1" class="btn btn-default"><i class="fa fa-download"></i> Descargar XML</a>
                    <a href="index.php?m=factel&clave={$invoiceid}&pdf=1" class="btn btn-default"><i class="fa fa-download"></i> Descargar PDF</a>
                    </div>
            {/if}
                <div class="pull-left btn-group btn-group-sm hidden-print">
                <a href="index.php?m=factel" class="btn btn-default"><i class="fa fa-arrow-left"></i> Validar otra factura</a>
                </div>
            <hr>
            <br>
            <div class="row">
                <div class="col-sm-6">
                    <strong>{$LANG.invoicesinvoicedto}:</strong>
                    <address class="small-text">
                        {if $clientsdetails_companyname}{$clientsdetails_companyname}<br />{/if}
                        {$clientsdetails_firstname} {$clientsdetails_lastname}<br />
                        {$clientsdetails_address1}, {$clientsdetails_address2}<br />
                </div>
                <div class="col-sm-6 text-right-sm">
                    <strong>{$LANG.invoicesdatecreated}:</strong><br>
                    <span class="small-text">
                        {$date}<br><br>
                    </span>
                </div>
            </div>

            <br />

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><strong>{$LANG.invoicelineitems}</strong></h3>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-condensed">
                            <thead>
                                <tr>
                                    <td><strong>{$LANG.invoicesdescription}</strong></td>
                                    <td width="20%" class="text-center"><strong>{$LANG.invoicesamount}</strong></td>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach from=$invoiceitems item=item}
                                    <tr>
                                        <td>{$item.description}{if $item.taxed eq "true"} *{/if}</td>
                                        <td class="text-center">{$item.amount}</td>
                                    </tr>
                                {/foreach}
                                <tr>
                                    <td class="total-row text-right"><strong>{$LANG.invoicessubtotal}</strong></td>
                                    <td class="total-row text-center">{$subtotal}</td>
                                </tr>
                                {if $taxrate}
                                    <tr>
                                        <td class="total-row text-right"><strong>{$taxrate}% {$taxname}</strong></td>
                                        <td class="total-row text-center">{$tax}</td>
                                    </tr>
                                {/if}
                                {if $taxrate2}
                                    <tr>
                                        <td class="total-row text-right"><strong>{$taxrate2}% {$taxname2}</strong></td>
                                        <td class="total-row text-center">{$tax2}</td>
                                    </tr>
                                {/if}
                                <tr>
                                    <td class="total-row text-right"><strong>{$LANG.invoicescredit}</strong></td>
                                    <td class="total-row text-center">{$credit}</td>
                                </tr>
                                <tr>
                                    <td class="total-row text-right"><strong>{$LANG.invoicestotal}</strong></td>
                                    <td class="total-row text-center">{$total}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        {/if}

    </div>

</body>
</html>

<template>
            <main class="main">
            <!-- Breadcrumb -->
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/">Escritorio</a></li>
            </ol>
            <div class="container-fluid">
                <div class="loader" v-if="loading"></div>
                <!-- Ejemplo de tabla Listado -->
                <div class="card">
                    <div class="card-header">
                        <i class="fa fa-align-justify"></i>
                        <template v-if="tipo==1">
                            Liga de Pago Única
                        </template>
                        <template v-else-if="tipo==2">
                            Liga de Pago Domiciliación/Recurrente
                        </template>
                        <template v-else-if="tipo==3">
                            Referencia SPEI
                        </template>
                        <template v-else-if="tipo==4">
                            Liga de Pago Terminal
                        </template>
                        <template v-else>
                            Generación de Ligas
                        </template>
                        <button type="button" @click="abrirModal('transaccion','registrar')" class="btn btn-secondary">
                            <i class="icon-plus"></i>&nbsp;Nuevo
                        </button> &nbsp;
                        <button type="button" v-if="tipo==1 || tipo==2" @click="abrirModalImportar()" class="btn btn-danger btn-sm">
                            <i class="icon-cloud-upload"></i>&nbsp;Importar
                        </button> &nbsp;
                        <button type="button" @click="descargarExportar()" class="btn btn-success btn-sm">
                            <i class="icon-cloud-download"></i>&nbsp;Exportar
                        </button> &nbsp;
                    </div>
                    <div class="card-body">
                        <div class="form-group row cdc-list-toolbar">
                            <div class="col-xl-6 col-lg-8 col-md-10 col-sm-12">
                                <div class="input-group">
                                    <select class="form-control col-lg-3 col-md-3 col-sm-4" v-model="criterio">
                                      <option value="ClientReference">Ref. Cliente</option>
                                      <option value="Reference">Ref. Transacción</option>
                                      <template v-if="tipo==3">
                                        <option value="Clabe">CLABE</option>
                                      </template>
                                      <template v-else>
                                        <option value="responseReference">Ref. Respuesta</option>
                                      </template>                                      
                                      <option value="Description">Descripción</option>
                                      <option value="cliente_nombre">Cliente</option>
                                    </select>
                                    <input type="text" v-model="buscar" @keyup.enter="listarTransaccion(1,buscar,criterio)" class="form-control" placeholder="Texto a buscar">
                                    <button type="submit" @click="listarTransaccion(1,buscar,criterio)" class="btn btn-primary"><i class="fa fa-search"></i> Buscar</button>
                                </div>
                            </div>
                        </div>
                        <div class="cdc-table-shell">
                        <table class="table table-bordered table-striped table-sm cdc-responsive-table">
                            <thead>
                                <tr>
                                    <th class="text-center">Opciones
                                        <select v-model="offset" @change="listarTransaccion(1,buscar,criterio)">
                                            <option value="10" selected>10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                        </select>
                                    </th>
                                    <th class="text-center">Folio</th>
                                    <th class="text-center">Fecha</th>
                                    <th class="text-center">Cliente</th>
                                    <template v-if="tipo==1 || tipo==2">
                                        <th class="text-center">Forma de Pago</th>
                                    </template>
                                    <th class="text-center">Descripción</th>
                                    <th class="text-center">Referencia</th>                                                                        
                                    <th class="text-center">Monto</th>
                                    <th class="text-center">Fecha de Expiración</th>
                                    <template v-if="tipo==1 || tipo==2 || tipo==4">
                                        <th class="text-center">URL</th>
                                    </template>
                                    <th class="text-center">Respuesta</th>                                    
                                    <th class="text-center">Usuario</th>
                                    <template v-if="(tipo==2 || tipo==3)">
                                        <template v-if="tipo==2">
                                            <th class="text-center">Frecuencia</th>
                                        </template>
                                        <th class="text-center">Status
                                            <select v-model="status" @change="listarTransaccion(1,buscar,criterio)">
                                                <option value="99" selected>Todos</option>
                                                <option value="0">Pendiente</option>
                                                <option value="1">Activo</option>
                                                <option value="2">Cancelado</option>
                                                <option value="3">Pagado</option>
                                                <option value="4">Vencido</option>
                                            </select>
                                        </th>
                                    </template>
                                    <th class="text-center">Productivo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="transaccion in arrayTransaccion" :key="transaccion.id">
                                    <td class="text-center">
                                        <button type="button" @click="abrirModal('transaccion','actualizar',transaccion)" class="btn btn-warning btn-sm cdc-action-button" title="Editar transacción" aria-label="Editar transacción">
                                          <i class="icon-pencil"></i>
                                        </button> &nbsp;
                                        <button type="button" @click="abrirModal('transaccion','ver',transaccion)" class="btn btn-success btn-sm cdc-action-button" title="Ver transacción" aria-label="Ver transacción">
                                          <i class="icon-eye"></i>
                                        </button> &nbsp;                                                                               
                                        <template v-if="(tipo==2 || tipo==4) && transaccion.condicion==1 && transaccion.responseReference != null"><button type="button" @click="rechazarTransaccion(transaccion.id)" class="btn btn-danger btn-sm cdc-action-button" title="Cancelar transacción" aria-label="Cancelar transacción">
                                            <i class="icon-close"></i>
                                        </button></template>
                               
                                    </td>
                                    <td v-text="transaccion.folio" class="text-center"></td>
                                    <td class="text-center">
                                        <span class="cdc-date-stack">
                                            <span>{{ $formatDateMx(transaccion.fecha) }}</span>
                                            <span class="cdc-date-stack__time">{{ $formatTimeMx(transaccion.fecha) }}</span>
                                        </span>
                                    </td>
                                    <td v-text="transaccion.razon_social" class="text-center"></td>
                                    <template v-if="tipo==1 || tipo==2">
                                        <td class="text-center">
                                            <template v-if="transaccion.PaymentTypes=='401'">
                                                Visa y Mastercard
                                            </template>
                                            <template v-else-if="transaccion.PaymentTypes=='1002'">
                                                American Express
                                            </template>
                                            <template v-else-if="transaccion.PaymentTypes=='41'">
                                                Visa y Mastercard
                                            </template>
                                            <template v-else-if="transaccion.PaymentTypes=='102'">
                                                American Express
                                            </template>                                        
                                            <template v-else>
                                                NA
                                            </template>
                                        </td>
                                    </template>
                                    <td v-text="transaccion.Description" class="text-center"></td>                                    
                                    <td v-text="transaccion.ClientReference" class="text-center"></td>                                    
                                    <td class="text-center">{{ $formatCurrency(transaccion.Amount / 100) }}</td>
                                    <td class="text-center">{{ $formatDateMx(transaccion.ExpirationDate) }}</td>
                                    <template v-if="tipo==1 || tipo==2">
                                        <td class="text-center">                                        
                                            <template v-if="transaccion.url!=null">
                                                <button type="button" @click="openURL(transaccion.url)" class="btn btn-success btn-sm cdc-action-button" :title="transaccion.url" aria-label="Abrir URL de pago">
                                                <i class="icon-globe"></i>
                                                </button> &nbsp;
                                            </template>                                        
                                        </td>
                                    </template>
                                    <template v-if="tipo==4">
                                        <td class="text-center">
                                            <template v-if="transaccion.responseReference != null && transaccion.condicion == 1">
                                                <button type="button" @click="openLector(transaccion.responseReference)" class="btn btn-success btn-sm cdc-action-button" :title="transaccion.responseReference" aria-label="Abrir liga terminal">
                                                        <i class="icon-globe"></i>
                                                </button> &nbsp;
                                            </template>
                                        </td>
                                    </template>
                                    <td class="text-center">
                                        <button type="button" @click="abrirModal('transaccion','respuesta',transaccion)" class="btn btn-warning btn-sm cdc-action-button" title="Ver respuesta" aria-label="Ver respuesta">
                                          <i class="icon-folder"></i>
                                        </button> &nbsp; 
                                    </td>                                                                        
                                    <td v-text="transaccion.usuario" class="text-center"></td>
                                    <template v-if="(tipo==2 || tipo==3)">
                                        <template v-if="tipo==2">
                                            <td class="text-center">
                                                <template v-if="transaccion.frecuencia=='1'">
                                                    Semanal
                                                </template>
                                                <template v-else-if="transaccion.frecuencia=='2'">
                                                    Mensual
                                                </template>
                                                <template v-else-if="transaccion.frecuencia=='3'">
                                                    Bimestral
                                                </template>
                                                <template v-else-if="transaccion.frecuencia=='4'">
                                                    Semestral
                                                </template> 
                                                <template v-else-if="transaccion.frecuencia=='5'">
                                                    Anual
                                                </template>                                         
                                                <template v-else>
                                                    NA
                                                </template>
                                            </td>
                                        </template>
                                        <td class="text-center">
                                            <div v-if="(transaccion.condicion==1)">
                                                <span class="badge badge-success">Activo</span>
                                            </div>
                                            <div v-else-if="(transaccion.condicion==2)">
                                                <span class="badge badge-danger">Cancelado</span>
                                            </div>
                                            <div v-else-if="(transaccion.condicion==3)">
                                                <span class="badge badge-success">Pagado</span>
                                            </div>
                                            <div v-else-if="(transaccion.condicion==4)">
                                                <span class="badge badge-warning">Vencido</span>
                                            </div>
                                            <div v-else-if="(transaccion.condicion==0)">
                                                <span class="badge badge-warning">Pendiente</span>
                                            </div>                                            
                                            <div v-else>
                                                <span class="badge badge-warning">Desconocido</span>
                                            </div>                                        
                                        </td>
                                    </template>
                                    <td>
                                        <div v-if="transaccion.productivo==1">
                                            <span class="badge badge-success">Si</span>
                                        </div>
                                        <div v-else>
                                            <span class="badge badge-warning">No</span>
                                        </div>
                                    </td>
                                </tr>                                
                            </tbody>
                        </table>
                        </div>
                        <nav>
                            <ul class="pagination cdc-pagination">
                                <li class="page-item" v-if="pagination.current_page > 1">
                                    <a class="page-link" href="#" @click.prevent="cambiarPagina(pagination.current_page - 1,buscar,criterio)">Ant</a>
                                </li>
                                <li class="page-item" v-for="page in pagesNumber" :key="page" :class="[page == isActived ? 'active' : '']">
                                    <a class="page-link" href="#" @click.prevent="cambiarPagina(page,buscar,criterio)" v-text="page"></a>
                                </li>
                                <li class="page-item" v-if="pagination.current_page < pagination.last_page">
                                    <a class="page-link" href="#" @click.prevent="cambiarPagina(pagination.current_page + 1,buscar,criterio)">Sig</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
                <!-- Fin ejemplo de tabla Listado -->
            </div>
            <!--Inicio del modal agregar/actualizar-->
            <div class="modal fade" tabindex="-1" :class="{'mostrar' : modal}" role="dialog" aria-labelledby="myModalLabel" style="overflow-y: scroll;display: none;" aria-hidden="true">
                <fieldset :disabled="loading">
                <div class="modal-dialog modal-primary modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title" v-text="tituloModal"></h4>
                            <button type="button" class="close" @click="cerrarModal()" aria-label="Close">
                              <span aria-hidden="true">×</span>
                            </button>
                        </div>                        
                        <div class="modal-body">                            
                            <form action="" method="post" enctype="multipart/form-data" class="form-horizontal">
                            
                                <template v-if="tipoAccion!=4">
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Cliente *</label>
                                    <div class="col-md-9">
                                        <select class="form-control" v-model="idcliente" :readonly="tipoAccion == 3">
                                            <option value="0" disabled>Seleccione un cliente</option>
                                            <option v-for="cliente in arrayCliente" :key="cliente.id" :value="cliente.id" v-text="cliente.razon_social"></option>
                                        </select>                                        
                                    </div>
                                </div>
                                <template v-if="tipo==1 || tipo==2">
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Forma de pago *</label>
                                        <div class="col-md-9">
                                            <select class="form-control" v-model="PaymentTypes" :readonly="tipoAccion == 2 || tipoAccion == 3">
                                                <option value="0" disabled>Seleccione una forma de pago</option>
                                                <template v-if="(esProductivo==1)">
                                                    <option value="41">Visa y Mastercard</option>
                                                    <option value="102">American Express</option>
                                                </template>
                                                <template v-if="(esProductivo==0)">
                                                    <option value="401">Visa y Mastercard</option>
                                                    <option value="1002">American Express</option>
                                                </template>
                                            </select>                                        
                                        </div>
                                    </div>
                                </template>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Descripción *</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="Description" class="form-control" placeholder="Descripción del pago" :readonly="tipoAccion == 2 || tipoAccion == 3">
                                        
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Monto *</label>
                                    <div class="col-md-9">
                                        <input type="number" v-model.number="Amount" class="form-control" placeholder="0" :readonly="tipoAccion == 2 || tipoAccion == 3">
                                        
                                    </div>
                                </div> 
                                <template v-if="tipo==3">
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Sin Fecha de Expiración</label>
                                        <div class="col-md-9">
                                            <input type="checkbox" v-model="SinExpirationDate" @change="sinFecha()" class="form-control" :readonly="tipoAccion == 2 || tipoAccion == 3">                                            
                                        </div>
                                    </div>
                                </template>
                                <template v-if="tipo!=4">
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Fecha de Expiración</label>
                                    <div class="col-md-9">
                                        <input type="date" v-model="ExpirationDate" class="form-control" placeholder="dd/mm/yyyy" :readonly="tipoAccion == 2 || tipoAccion == 3 || SinExpirationDate == 1">                                        
                                    </div>
                                </div>
                                </template>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Referencia *</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="ClientReference" class="form-control" placeholder="Referencia de la operación" :readonly="tipoAccion == 3">                                        
                                    </div>
                                </div>
                                <template v-if="tipo==2">
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Frecuencia *</label>
                                        <div class="col-md-9">
                                            <select class="form-control" v-model="frecuencia" @change="selectFechaPago()" :readonly="tipoAccion == 2 || tipoAccion == 3">
                                                <option value="0" disabled>Seleccione la frecuencia del cargo</option>                                                
                                                <option value="1">Semanal</option>
                                                <option value="2">Mensual</option>
                                                <option value="3">Bimestral</option>
                                                <option value="4">Semestral</option>
                                                <option value="5">Anual</option>
                                            </select>                                        
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Establecer fecha</label>
                                        <div class="col-md-1">
                                            <input type="checkbox" v-model="establecerFecha" class="form-control">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Fecha de Próximo Cargo</label>
                                        <div class="col-md-4">
                                            <input type="date" v-model="ProximoCargo" class="form-control" placeholder="dd/mm/yyyy"  @change="validarFechaPago()" :readonly="establecerFecha == 0">
                                        </div>
                                        
                                    </div>
                                </template>
                                <div v-show="errorTransaccion" class="form-group row div-error">
                                    <div class="text-center text-error">
                                        <div v-for="error in errorMostrarMsjTransaccion" :key="error" v-text="error">
                                        </div>
                                    </div>
                                </div>
                                </template>
                                <template v-if="tipoAccion==4">
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Ref. Transacción</label>
                                        <div class="col-md-9">
                                            {{responseReference}}
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Ref. Emisor</label>
                                        <div class="col-md-9">
                                            {{referenceEmisor}}
                                        </div>
                                    </div>
                                    <template v-if="tipo==1 || tipo==2">
                                        <div class="form-group row">
                                            <label class="col-md-3 form-control-label" for="text-input">Código</label>
                                            <div class="col-md-9">
                                                {{code}}
                                            </div>
                                        </div>
                                    </template>
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Mensaje</label>
                                        <div class="col-md-9">
                                            {{message}}
                                        </div>
                                    </div>
                                    <template v-if="tipo==1 || tipo==2">
                                        <div class="form-group row">
                                            <label class="col-md-3 form-control-label" for="text-input">URL</label>
                                            <div class="col-md-9">
                                                <a :href="url" target="_blank"> {{url}} </a>
                                            </div>
                                        </div>
                                    </template>
                                    <template v-if="tipo==3">
                                        <div class="form-group row">
                                            <label class="col-md-3 form-control-label" for="text-input">Error</label>
                                            <div class="col-md-9">
                                                {{Error}}
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="col-md-3 form-control-label" for="text-input">Fecha</label>
                                            <div class="col-md-9">
                                                {{Fecha}}
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="col-md-3 form-control-label" for="text-input">CLABE</label>
                                            <div class="col-md-9">
                                                {{Clabe}}
                                            </div>
                                        </div>
                                    </template>
                                    <template v-if="tipo==4">
                                        <div class="form-group row">
                                            <label class="col-md-3 form-control-label" for="text-input">Code QR</label>
                                            <div class="col-md-9">
                                                {{codeQR}}
                                            </div>
                                        </div>
                                    </template>
                                </template>                                
                            </form>                            
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="cerrarModal()">Cerrar</button>
                            <button type="button" v-if="tipoAccion==1" class="btn btn-primary" @click="registrarTransaccion()">Guardar</button>
                            <button type="button" v-if="tipoAccion==2" class="btn btn-primary" @click="actualizarTransaccion()">Actualizar</button>
                        </div>
                    
                    </div>
                    <!-- /.modal-content -->
                </div>                
                <!-- /.modal-dialog -->
            </fieldset>                
            </div>
            <!--Fin del modal-->

            <div class="modal fade" tabindex="-1" :class="{'mostrar' : modalImportar}" role="dialog" aria-labelledby="myModalLabel" style="overflow-y: scroll;display: none;" aria-hidden="true">
                <fieldset :disabled="loading">
                <div class="modal-dialog modal-danger" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">Importar Excel para generación de ligas</h4>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Archivo Excel (.xlsx, .xls)</label>
                                <input type="file" class="form-control" accept=".xlsx,.xls" :disabled="importando" @change="onChangeArchivoImportar($event)">
                            </div>
                            <div v-if="importError" class="alert alert-danger">{{ importError }}</div>
                            <div v-if="importTotal > 0" class="mb-2">
                                <strong>Progreso:</strong> {{ importProcessed }} / {{ importTotal }}
                                <div class="progress">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" :style="{width: importProgress + '%'}">{{ importProgress }}%</div>
                                </div>
                            </div>
                            <div v-if="importResumenVisible" class="alert alert-info mt-2">
                                <div><strong>Resumen:</strong></div>
                                <div>Total registros: {{ importTotal }}</div>
                                <div>Generadas: {{ importGenerated }}</div>
                                <div>Errores: {{ importErrors }}</div>
                                <div>Error cliente: {{ importErrorCliente }}</div>
                                <div>Error monto: {{ importErrorMonto }}</div>
                                <div>Error fecha: {{ importErrorFecha }}</div>
                                <div>Error forma de pago: {{ importErrorPago }}</div>
                                <div v-if="tipo==2">Error frecuencia: {{ importErrorFrecuencia }}</div>
                                <div>Omitidas por cancelación: {{ importCancelledOmitted }}</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-success" :disabled="importando || !importArchivo" @click="generarImportacion()">Generar</button>
                            <button type="button" class="btn btn-secondary" @click="cancelarImportacionConConfirmacion()">{{ textoBotonImportarSecundario }}</button>
                            <button type="button" class="btn btn-primary" v-if="importResumenVisible" @click="descargarLogImportacion()">Descargar log</button>
                        </div>
                    </div>
                </div>
            </fieldset>
            </div>
        </main>
</template>
<script>
    
    export default {
        props: ['tipo','productivo'],
        data (){
            return {                
                transaccion_id: 0,
                idcliente : 0,
                PaymentTypes : 0,
                Description : '',
                Amount : 0,
                ExpirationDate : null,
                SinExpirationDate : 0,
                ClientReference : '',
                url : '',
                code : '',
                message : '',
                responseReference : '',
                referenceEmisor : '',
                Error : '',
                Fecha : '',
                Clabe : '',
                codeQR : '',
                frecuencia: 0,
                ProximoCargo : null,
                establecerFecha: 0,
                esProductivo: this.productivo,
                arrayTransaccion : [],
                arrayCliente : [],
                modal : 0,
                tituloModal : '',
                tipoAccion : 0,
                errorTransaccion : 0,
                errorMostrarMsjTransaccion : [],
                pagination : {
                    'total' : 0,
                    'current_page' : 0,
                    'per_page' : 0,
                    'last_page' : 0,
                    'from' : 0,
                    'to' : 0,
                },
                offset : 10,
                status: 99,
                criterio : 'Reference',
                buscar : '',
                loading: false,
                modalImportar: 0,
                importArchivo: null,
                importando: false,
                importId: '',
                importTotal: 0,
                importProcessed: 0,
                importGenerated: 0,
                importErrors: 0,
                importErrorCliente: 0,
                importErrorMonto: 0,
                importErrorFecha: 0,
                importErrorPago: 0,
                importErrorFrecuencia: 0,
                importCancelledOmitted: 0,
                importProgress: 0,
                importError: '',
                importResumenVisible: false,
                cancelRequested: false
            }
        },
        computed:{
            isActived: function(){
                return this.pagination.current_page;
            },
            pagesNumber: function() {
                return this.$paginationPages(this.pagination);
            },
            textoBotonImportarSecundario: function(){
                return this.importando ? 'Cancelar' : 'Cerrar';
            }
        },
        methods : {
            listarTransaccion (page,buscar,criterio){
                let me=this;
                var url= '/transaccion?page=' + page + '&buscar='+ encodeURIComponent(buscar || '') + '&criterio='+ encodeURIComponent(criterio || 'Reference') + '&offset='+ me.offset + '&tipo='+ me.tipo + '&status='+ me.status;
                axios.get(url).then(function (response) {
                    var respuesta= response.data;
                    me.arrayTransaccion = respuesta.transacciones.data;
                    me.pagination= respuesta.pagination;
                })
                .catch(function (error) {
                    swal(
                        'Error!',
                        'Error al listar los registros. Error: ' + error,
                        'error'
                        ) 
                    console.log(error);
                });
            },
            selectFechaPago(){
                let me = this;                    
                var currentDate = new Date();
                if(me.frecuencia != null && me.frecuencia != 0){
                    if(me.frecuencia == 1){
                        var proximoDate = new Date(currentDate.setDate(currentDate.getDate()+7));
                        me.ProximoCargo = proximoDate.getFullYear()+'-'+String((proximoDate.getMonth()+1)).padStart(2, '0')+'-'+String(proximoDate.getDate()).padStart(2, '0');
                    }else if(me.frecuencia == 2){                        
                        var proximoDate = new Date(currentDate.setMonth(currentDate.getMonth()+1));
                        me.ProximoCargo = proximoDate.getFullYear()+'-'+String((proximoDate.getMonth()+1)).padStart(2, '0')+'-'+String(proximoDate.getDate()).padStart(2, '0');
                    }else if(me.frecuencia == 3){
                        var proximoDate = new Date(currentDate.setMonth(currentDate.getMonth()+2));
                        me.ProximoCargo = proximoDate.getFullYear()+'-'+String((proximoDate.getMonth()+1)).padStart(2, '0')+'-'+String(proximoDate.getDate()).padStart(2, '0');
                    }else if(me.frecuencia == 4){
                        var proximoDate = new Date(currentDate.setMonth(currentDate.getMonth()+6));
                        me.ProximoCargo = proximoDate.getFullYear()+'-'+String((proximoDate.getMonth()+1)).padStart(2, '0')+'-'+String(proximoDate.getDate()).padStart(2, '0');
                    }else if(me.frecuencia == 5){
                        var proximoDate = new Date(currentDate.setMonth(currentDate.getMonth()+12));
                        me.ProximoCargo = proximoDate.getFullYear()+'-'+String((proximoDate.getMonth()+1)).padStart(2, '0')+'-'+String(proximoDate.getDate()).padStart(2, '0');
                    }
                }                
            },
            validarFechaPago(){
                let me = this;                    
                var currentDate = new Date();
                var proximoDate = new Date(me.ProximoCargo);
                if(proximoDate <= currentDate){
                    swal(
                        'Error en la fecha!',
                        'La fecha no puede ser menor o igual a la actual.',
                        'error'
                    )
                    if(me.frecuencia == 1){
                        var proximoDate = new Date(currentDate.setDate(currentDate.getDate()+7));
                        me.ProximoCargo = proximoDate.getFullYear()+'-'+String((proximoDate.getMonth()+1)).padStart(2, '0')+'-'+String(proximoDate.getDate()).padStart(2, '0');
                    }else if(me.frecuencia == 2){                        
                        var proximoDate = new Date(currentDate.setMonth(currentDate.getMonth()+1));
                        me.ProximoCargo = proximoDate.getFullYear()+'-'+String((proximoDate.getMonth()+1)).padStart(2, '0')+'-'+String(proximoDate.getDate()).padStart(2, '0');
                    }else if(me.frecuencia == 3){
                        var proximoDate = new Date(currentDate.setMonth(currentDate.getMonth()+2));
                        me.ProximoCargo = proximoDate.getFullYear()+'-'+String((proximoDate.getMonth()+1)).padStart(2, '0')+'-'+String(proximoDate.getDate()).padStart(2, '0');
                    }else if(me.frecuencia == 4){
                        var proximoDate = new Date(currentDate.setMonth(currentDate.getMonth()+6));
                        me.ProximoCargo = proximoDate.getFullYear()+'-'+String((proximoDate.getMonth()+1)).padStart(2, '0')+'-'+String(proximoDate.getDate()).padStart(2, '0');
                    }else if(me.frecuencia == 5){
                        var proximoDate = new Date(currentDate.setMonth(currentDate.getMonth()+12));
                        me.ProximoCargo = proximoDate.getFullYear()+'-'+String((proximoDate.getMonth()+1)).padStart(2, '0')+'-'+String(proximoDate.getDate()).padStart(2, '0');
                    }
                }                
            },
            sinFecha(){
                let me = this;                    
                var proximoDate = new Date();                
                me.ExpirationDate = proximoDate.getFullYear()+'-'+String((proximoDate.getMonth()+1)).padStart(2, '0')+'-'+String(proximoDate.getDate()).padStart(2, '0');
            },
            formatDatePickr(date){
                var proximoDate = new Date(date.getDate());
                var finalDate = proximoDate.getFullYear()+'-'+String((proximoDate.getMonth()+1)).padStart(2, '0')+'-'+String(proximoDate.getDate()).padStart(2, '0');
                return finalDate;
            },
            descargarExportar (){
                let me = this;

                axios({
                    url: '/transaccion/exportar?tipo='+ me.tipo,
                    meth: 'GET',
                    responseType: 'blob'
                    }).then(function (response) {                    
                        var fileURL = window.URL.createObjectURL(new Blob([response.data]));
                        var fileLink = document.createElement('a');
                        
                        fileLink.href = fileURL;
                        fileLink.setAttribute('download', 'transacciones.csv');
                        document.body.appendChild(fileLink);
                        
                        fileLink.click();
                        fileLink.remove();
                }).catch(function (error) {
                    console.log(error);
                        swal(
                        'Error!',
                        'Error al descargar el archivo.',
                        'error'
                        )
                }); 
            },
            abrirModalImportar(){
                this.modalImportar = 1;
                this.importArchivo = null;
                this.importando = false;
                this.importId = '';
                this.importTotal = 0;
                this.importProcessed = 0;
                this.importGenerated = 0;
                this.importErrors = 0;
                this.importErrorCliente = 0;
                this.importErrorMonto = 0;
                this.importErrorFecha = 0;
                this.importErrorPago = 0;
                this.importErrorFrecuencia = 0;
                this.importCancelledOmitted = 0;
                this.importProgress = 0;
                this.importError = '';
                this.importResumenVisible = false;
                this.cancelRequested = false;
            },
            onChangeArchivoImportar(event){
                var files = event.target.files || [];
                this.importArchivo = files.length > 0 ? files[0] : null;
            },
            generarImportacion(){
                let me = this;
                if(!me.importArchivo){
                    me.importError = 'Debe seleccionar un archivo Excel.';
                    return;
                }

                me.importError = '';
                me.importando = true;
                me.importResumenVisible = false;
                me.cancelRequested = false;

                let data = new FormData();
                data.append('archivo', me.importArchivo);
                data.append('tipo', me.tipo);

                axios.post('/transaccion/importar/iniciar', data)
                    .then(function(response){
                        me.importId = response.data.import_id;
                        me.importTotal = response.data.total;
                        me.procesarSiguienteImportacion();
                    })
                    .catch(function(error){
                        me.importando = false;
                        me.importError = error.response && error.response.data && error.response.data.error ? error.response.data.error : 'No fue posible iniciar la importación.';
                    });
            },
            procesarSiguienteImportacion(){
                let me = this;
                if(me.cancelRequested || !me.importId){
                    me.importando = false;
                    return;
                }

                axios.post('/transaccion/importar/procesar', {import_id: me.importId})
                .then(function(response){
                    me.actualizarResumenImportacion(response.data);
                    if(response.data.status === 'in_progress' && !me.cancelRequested){
                        me.procesarSiguienteImportacion();
                    } else {
                        me.importando = false;
                        me.importResumenVisible = true;
                        me.listarTransaccion(1,me.buscar,me.criterio);
                    }
                })
                .catch(function(error){
                    me.importando = false;
                    me.importError = 'Error en el procesamiento de la importación.';
                    console.log(error);
                });
            },
            actualizarResumenImportacion(data){
                this.importTotal = data.total;
                this.importProcessed = data.processed;
                this.importGenerated = data.generated;
                this.importErrors = data.errors;
                this.importErrorCliente = data.error_cliente;
                this.importErrorMonto = data.error_monto;
                this.importErrorFecha = data.error_fecha;
                this.importErrorPago = data.error_pago;
                this.importErrorFrecuencia = data.error_frecuencia;
                this.importCancelledOmitted = data.cancelled_omitted;
                this.importProgress = this.importTotal > 0 ? Math.round((this.importProcessed * 100) / this.importTotal) : 0;
            },
            cancelarImportacionConConfirmacion(){
                let me = this;
                if(!me.importando || !me.importId){
                    me.modalImportar = 0;
                    return;
                }

                swal({
                    title: '¿Está seguro de detener el proceso?',
                    type: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, detener',
                    cancelButtonText: 'Continuar',
                    confirmButtonClass: 'btn btn-danger',
                    cancelButtonClass: 'btn btn-success',
                    buttonsStyling: false,
                    reverseButtons: true
                }).then((result) => {
                    if(!result.value){
                        return;
                    }

                    me.cancelRequested = true;
                    axios.post('/transaccion/importar/cancelar', {import_id: me.importId})
                    .then(function(response){
                        me.actualizarResumenImportacion(response.data);
                        me.importando = false;
                        me.importResumenVisible = true;
                    }).catch(function(error){
                        me.importError = 'No fue posible cancelar la importación.';
                        console.log(error);
                    });
                });
            },
            descargarLogImportacion(){
                let me = this;
                if(!me.importId){
                    return;
                }

                axios({
                    url: '/transaccion/importar/log?import_id=' + me.importId,
                    meth: 'GET',
                    responseType: 'blob'
                }).then(function (response) {
                    var fileURL = window.URL.createObjectURL(new Blob([response.data]));
                    var fileLink = document.createElement('a');
                    fileLink.href = fileURL;
                    fileLink.setAttribute('download', 'log_importacion_ligas.xlsx');
                    document.body.appendChild(fileLink);
                    fileLink.click();
                    fileLink.remove();
                }).catch(function (error) {
                    me.importError = 'Error al descargar el log.';
                    console.log(error);
                });
            },
            cambiarPagina(page,buscar,criterio){
                let me = this;
                //Actualiza la página actual
                me.pagination.current_page = page;
                //Envia la petición para visualizar la data de esa página
                me.listarTransaccion(page,buscar,criterio);
            },
            openURL (liga) {   
                window.open(liga, "_blank");
            },
            openLector (reference) {   
                window.open("https://pagadetodo.mx/PaymentProcess/ReferenceId=" + reference, "_blank");
            },
            selectCliente(){
                let me=this;
                var url= '/cliente/selectCliente';
                axios.get(url).then(function (response) {
                    //console.log(response);
                    var respuesta = response.data;
                    me.arrayCliente = respuesta.clientes;
                })
                .catch(function (error) {
                    swal(
                        'Error!',
                        'Error al realizar el listado de clientes. Error: ' + error,
                        'error'
                        ) 
                    console.log(error);
                });
            },                       
            registrarTransaccion(){                
                if (this.validarTransaccion()){                    
                    return;
                }
                this.loading =  true;
                let me = this;

                let data = new FormData();                
                data.append('idcliente', this.idcliente);
                data.append('PaymentTypes', this.PaymentTypes);
                data.append('Description', this.Description);
                data.append('Amount', this.Amount);
                data.append('ExpirationDate', this.ExpirationDate);
                data.append('ClientReference', this.ClientReference);
                data.append('tipo', this.tipo);
                data.append('frecuencia', this.frecuencia);
                data.append('ProximoCargo', this.ProximoCargo);

                var metodo = '';

                if(me.tipo == 1) metodo = '/transaccion/registrar';
                else if(me.tipo == 2) metodo = '/transaccion/registrarDom';
                else if(me.tipo == 3) metodo = '/transaccion/registrarSpei';
                else if(me.tipo == 4) metodo = '/transaccion/registrarLector';

                 axios.post(metodo, data).then(function (response) {                    

                    var respuesta = response.data;
                    var error = respuesta.error;
                    var mensaje = respuesta.msg;
                    console.log(error);

                    if(error == ''){
                        swal(
                        'Registro exitoso!',
                        mensaje,
                        'success'
                        ) 
                        console.log("Se guardo.");
                    } else {
                        swal(
                        'Error!',
                        'Error al realizar el registro. Error: ' + error,
                        'error'
                        )
                    }
                    
                    me.cerrarModal();
                    me.listarTransaccion(1,'','Reference');
                }).catch(function (error) {                    
                        swal(
                        'Error!',
                        'Error al realizar el registro. Error: ' + error,
                        'error'
                        )                    
                    console.log(error);
                }).finally(() => {
                        this.loading =  false;
                });                      
            },
            actualizarTransaccion(){                
               if (this.validarTransaccion()){                    
                    return;
                }
                this.loading =  true;
                let me = this;

                axios.put('/transaccion/actualizar',{
                    'idcliente': this.idcliente,                    
                    'ClientReference': this.ClientReference,                    
                    'id': this.transaccion_id
                }).then(function (response) {
                    me.cerrarModal();
                    me.listarTransaccion(1,'','Reference');
                }).catch(function (error) {
                    swal(
                        'Error!',
                        'Error al realizar la actualización. Error: ' + error,
                        'error'
                        ) 
                    console.log(error);                  
                }).finally(() => {
                        this.loading =  false;
                });      
            },            
            rechazarTransaccion(id){              
                let me = this;
                swal({
                title: 'Esta seguro de cancelar esta transacción/cargo recurrente?',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Aceptar!',
                cancelButtonText: 'Cancelar',
                confirmButtonClass: 'btn btn-success',
                cancelButtonClass: 'btn btn-danger',
                buttonsStyling: false,
                reverseButtons: true
                }).then((result) => {
                    axios.put('/transaccion/rechazar',{
                        'id': id
                    }).then(function (response) {     
                        
                        var respuesta = response.data;
                        var error = respuesta.error;
                        var mensaje = respuesta.msg;

                        if(error == ''){
                            swal(
                            'Cancelación exitosa!',
                            mensaje,
                            'success'
                            ) 
                        } else {
                            swal(
                            'Error!',
                            'Error al realizar la cancelación. Error: ' + error,
                            'error'
                            )  
                        }

                        me.listarTransaccion(1,'','Reference');
                    }).catch(function (error) {
                        swal(
                        'Error!',
                        'Error al cancelar el cargo recurrente. Error: ' + error,
                        'error'
                        ) 
                        console.log(error);                        
                    });
                })
            },      
            validarTransaccion(){
                this.errorTransaccion=0;
                this.errorMostrarMsjTransaccion =[];

                if (!this.tipo) this.errorMostrarMsjTransaccion.push("El Tipo de transacción no puede estar vacío.");
                if(this.idcliente == 0) this.errorMostrarMsjTransaccion.push("Debe seleccionar un cliente.");                
                if (!this.Description) this.errorMostrarMsjTransaccion.push("El Description no puede estar vacío.");
                if (!this.Amount) this.errorMostrarMsjTransaccion.push("El apellido paterno no puede estar vacío.");
                if (!this.ClientReference) this.errorMostrarMsjTransaccion.push("El ClientReference no puede estar vacío.");

                if(this.tipo == 2){
                    if(this.frecuencia == 0) this.errorMostrarMsjTransaccion.push("Debe seleccionar la frecuencia del cargo.");
                    if(this.ProximoCargo == null) this.errorMostrarMsjTransaccion.push("No se estableció correctamente la fecha del próximo cargo.");
                }
                                    
                if(this.tipo < 3) {
                    if(this.PaymentTypes == 0) this.errorMostrarMsjTransaccion.push("Debe seleccionar una forma de pago.");
                }                    

                if (this.errorMostrarMsjTransaccion.length) this.errorTransaccion = 1;

                return this.errorTransaccion;
            },                       
            cerrarModal(){
                this.modal=0;
                this.tituloModal='';
                                
                this.idcliente=0;
                this.PaymentTypes=0;
                this.Description='';
                this.Amount=0;
                this.ExpirationDate=null;
                this.ClientReference='';
                this.url='';
                this.code='';
                this.message='';
                this.responseReference='';
                this.referenceEmisor='';
                this.Error='';
                this.Fecha='';
                this.Clabe='';
                this.frecuencia=0;
                this.ProximoCargo=null,
                this.esProductivo=this.productivo;
            },
            abrirModal(modelo, accion, data = []){
                switch(modelo){
                    case "transaccion":
                    {
                        switch(accion){
                            case 'registrar':
                            {
                                this.modal = 1;                                
                                this.tituloModal = 'Registrar Transacción';
                                this.idcliente = 0;
                                this.PaymentTypes = 0;
                                this.Description = '';
                                this.Amount = 0;
                                this.ExpirationDate = null;
                                this.ClientReference = '';
                                this.frecuencia = 0;
                                this.ProximoCargo = null;
                                this.tipoAccion = 1;
                                break;
                            }
                            case 'actualizar':
                            {                                
                                this.modal=1;
                                this.tituloModal = 'Actualizar Transacción';
                                this.transaccion_id = data['id'];
                                this.idcliente = data['idcliente'];
                                this.PaymentTypes = data['PaymentTypes'];
                                this.Description = data['Description'];
                                this.Amount = (parseFloat(data['Amount']) / 100.00);
                                this.ExpirationDate = data['ExpirationDate'];
                                this.ClientReference = data['ClientReference'];
                                this.frecuencia = data['frecuencia'];
                                this.ProximoCargo = data['ProximoCargo'];
                                this.esProductivo = data['productivo'];
                                this.Fecha = data['Date'];
                                this.Clabe = data['Clabe'];
                                this.codeQR = data['codeQR'];
                                this.tipoAccion = 2;
                                window.scrollTo(0,0);
                                break;
                            }
                            case 'ver':
                            {                                
                                this.modal=1;
                                this.tituloModal = 'Ver Transacción';                                
                                this.transaccion_id = data['id'];                                
                                this.idcliente = data['idcliente'];
                                this.PaymentTypes = data['PaymentTypes'];
                                this.Description = data['Description'];
                                this.Amount = (parseFloat(data['Amount']) / 100.00);
                                this.ExpirationDate = data['ExpirationDate'];                                
                                this.ClientReference = data['ClientReference'];
                                this.frecuencia = data['frecuencia'];
                                this.ProximoCargo = data['ProximoCargo'];
                                this.esProductivo = data['productivo'];
                                this.Fecha = data['Date'];
                                this.Clabe = data['Clabe'];
                                this.codeQR = data['codeQR'];
                                this.tipoAccion = 3;
                                window.scrollTo(0,0);
                                break;
                            }
                            case 'respuesta':
                            {                                
                                this.modal=1;
                                this.tituloModal = 'Respuesta';                                
                                this.transaccion_id = data['id'];                                
                                this.url = data['url'];
                                this.code = data['code'];
                                this.message = data['message'];
                                this.responseReference = data['responseReference'];
                                this.referenceEmisor = data['referenceEmisor'];
                                this.Error = data['Error'];
                                this.Fecha = data['Date'];
                                this.Clabe = data['Clabe'];
                                this.codeQR = data['codeQR'];
                                this.tipoAccion = 4;
                                window.scrollTo(0,0);
                                break;
                            }                                                                                  
                        }
                    }
                }

                this.selectCliente();
            }            
        },
        watch: {
            tipo: {
                handler(val){
                    this.listarTransaccion(1,this.buscar,this.criterio);
                },
                deep: true
            }
        },
        mounted() {
            this.listarTransaccion(1,this.buscar,this.criterio);
        }
    }
</script>
<style>    
    .modal-content{
        width: 100% !important;
        position: absolute !important;
    }
    .mostrar{
        display: list-item !important;
        opacity: 1 !important;
        position: absolute !important;
        background-color: #3c29297a !important;
    }
    .div-error{
        display: flex;
        justify-content: center;
    }
    .text-error{
        color: red !important;
        font-weight: bold;
    }
</style>

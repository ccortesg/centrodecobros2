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
                        <i class="fa fa-align-justify"></i> Cancelaciones Spei
                        <!--<button type="button" @click="abrirModal('cancelaspei','registrar')" class="btn btn-secondary">
                            <i class="icon-plus"></i>&nbsp;Nuevo
                        </button>--> &nbsp;
                        <button type="button" @click="descargarExportar()" class="btn btn-success btn-sm">
                            <i class="icon-cloud-download"></i>&nbsp;Exportar
                        </button> &nbsp;
                    </div>
                    <div class="card-body">
                        <div class="form-group row cdc-list-toolbar">
                            <div class="col-xl-6 col-lg-8 col-md-10 col-sm-12">
                                <div class="input-group">
                                    <select class="form-control col-lg-2 col-md-3 col-sm-4" v-model="criterio">
                                      <option value="clabe">Clabe</option>
                                      <option value="transaccion">Transacción</option>
                                      <option value="autorizacion">Autorización</option>
                                    </select>
                                    <input type="text" v-model="buscar" @keyup.enter="listarCancelaSpei(1,buscar,criterio)" class="form-control" placeholder="Texto a buscar">
                                    <button type="submit" @click="listarCancelaSpei(1,buscar,criterio)" class="btn btn-primary"><i class="fa fa-search"></i> Buscar</button>
                                </div>
                            </div>
                        </div>
                        <div class="cdc-table-shell">
                        <table class="table table-bordered table-striped table-sm cdc-responsive-table">
                            <thead>
                                <tr>
                                    <th class="text-center">Opciones
                                        <select v-model="offset" @change="listarCancelaSpei(1,buscar,criterio)">
                                            <option value="10" selected>10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                        </select>
                                    </th>
                                    <th class="text-center">Folio</th>
                                    <th class="text-center">Fecha</th>
                                    <th class="text-center">Clabe</th>
                                    <th class="text-center">Fecha Petición</th>
                                    <th class="text-center">Monto</th>
                                    <th class="text-center">Transacción</th>
                                    <th class="text-center">Código</th>
                                    <th class="text-center">Mensaje</th>
                                    <th class="text-center">Autorización</th>
                                    <th class="text-center">Enviada
                                        <select v-model="filtroEnviada" @change="listarCancelaSpei(1,buscar,criterio)">
                                            <option value="99" selected>Todos</option>
                                            <option value="0">No</option>
                                            <option value="1">Sí</option>
                                        </select>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="cancelaspei in arrayCancelaSpei" :key="cancelaspei.id">
                                    <td class="text-center">
                                        <button type="button" @click="abrirModal('cancelaspei','ver',cancelaspei)" class="btn btn-success btn-sm cdc-action-button" title="Ver cancelación SPEI" aria-label="Ver cancelación SPEI">
                                          <i class="icon-eye"></i>
                                        </button> &nbsp;
                                        <!--<button type="button" class="btn btn-danger btn-sm" @click="eliminarCancelaSpei(cancelaspei.id)">
                                            <i class="icon-trash"></i>
                                        </button>-->
                                    </td>
                                    <td v-text="cancelaspei.id" class="text-center"></td>
                                    <td class="text-center">
                                        <span class="cdc-date-stack">
                                            <span>{{ $formatDateMx(cancelaspei.fecha) }}</span>
                                            <span class="cdc-date-stack__time">{{ $formatTimeMx(cancelaspei.fecha) }}</span>
                                        </span>
                                    </td>
                                    <td v-text="cancelaspei.clabe" class="text-center"></td>
                                    <td class="text-center">
                                        <span class="cdc-date-stack">
                                            <span>{{ $formatDateMx(cancelaspei.fecha_peticion) }}</span>
                                            <span class="cdc-date-stack__time">{{ $formatTimeMx(cancelaspei.fecha_peticion) }}</span>
                                        </span>
                                    </td>
                                    <td class="text-center">{{ $formatCurrency(cancelaspei.monto / 100) }}</td>
                                    <td v-text="cancelaspei.transaccion" class="text-center"></td>
                                    <td v-text="cancelaspei.codigo" class="text-center"></td>                       
                                    <td v-text="cancelaspei.mensaje" class="text-center"></td>
                                    <td v-text="cancelaspei.autorizacion" class="text-center"></td>
                                    <td class="text-center">
                                        <div v-if="(cancelaspei.enviada==0)">
                                            <span class="badge badge-warning">No</span>
                                        </div>
                                        <div v-else-if="(cancelaspei.enviada==1)">
                                            <span class="badge badge-success">Sí</span>
                                        </div>
                                        <div v-else>
                                            <span class="badge badge-warning">Desconocido</span>
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
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Clabe</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="clabe" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                    </div>
                                </div>                                
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Fecha Petición</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="fecha_peticion" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                    </div>
                                </div> 
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Monto</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="monto" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                    </div>
                                </div>  
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Transacción</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="transaccion" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Código</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="codigo" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Autorización</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="autorizacion" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                    </div>
                                </div>                                
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Mensaje</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="mensaje" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Enviada</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="enviada" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                    </div>
                                </div>
                                <div v-show="errorCancelaSpei" class="form-group row div-error">
                                    <div class="text-center text-error">
                                        <div v-for="error in errorMostrarMsjCancelaSpei" :key="error" v-text="error">
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="cerrarModal()">Cerrar</button>
                            <button type="button" v-if="tipoAccion==1" class="btn btn-primary" @click="registrarCancelaSpei()">Guardar</button>
                            <button type="button" v-if="tipoAccion==2" class="btn btn-primary" @click="actualizarCancelaSpei()">Actualizar</button>
                        </div>
                    </div>
                    <!-- /.modal-content -->
                </div>
                <!-- /.modal-dialog -->
            </div>
            <!--Fin del modal-->
        </main>
</template>
<script>
    
    export default {
        props: ['tipo'],
        data (){
            return {                
                cancelaspei_id: 0,
                fecha : null,
                clabe : '',            
                fecha_peticion : '',
                monto : '',
                transaccion : '',
                codigo : '',
                autorizacion: '',
                mensaje : '',
                enviada : '',                
                arrayCancelaSpei : [],                
                modal : 0,
                tituloModal : '',
                tipoAccion : 0,
                errorCancelaSpei : 0,
                errorMostrarMsjCancelaSpei : [],
                pagination : {
                    'total' : 0,
                    'current_page' : 0,
                    'per_page' : 0,
                    'last_page' : 0,
                    'from' : 0,
                    'to' : 0,
                },
                offset : 10,
                filtroEnviada : 99,
                criterio : 'clabe',
                buscar : '',
                loading: false
            }
        },
        computed:{
            isActived: function(){
                return this.pagination.current_page;
            },
            pagesNumber: function() {
                return this.$paginationPages(this.pagination);
            }
        },
        methods : {
            listarCancelaSpei (page,buscar,criterio){
                let me=this;
                var url= '/cancelaspei?page=' + page + '&buscar='+ encodeURIComponent(buscar || '') + '&criterio='+ encodeURIComponent(criterio || 'clabe') + '&offset='+ me.offset + '&enviada='+ me.filtroEnviada;
                axios.get(url).then(function (response) {
                    var cancelaspei= response.data;
                    me.arrayCancelaSpei = cancelaspei.cancelaspei.data;
                    me.pagination= cancelaspei.pagination;
                })
                .catch(function (error) {
                    console.log(error);
                });
            },
            descargarExportar (){
                let me = this;

                axios({
                    url: '/cancelaspei/exportar',
                    meth: 'GET',
                    responseType: 'blob'
                    }).then(function (response) {                    
                        var fileURL = window.URL.createObjectURL(new Blob([response.data]));
                        var fileLink = document.createElement('a');
                        
                        fileLink.href = fileURL;
                        fileLink.setAttribute('download', 'cancelaspei.xls');
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
            cambiarPagina(page,buscar,criterio){
                let me = this;
                //Actualiza la página actual
                me.pagination.current_page = page;
                //Envia la petición para visualizar la data de esa página
                me.listarCancelaSpei(page,buscar,criterio);
            },            
            cerrarModal(){
                this.modal=0;
                this.tituloModal='';
                
                this.fecha=null;
                this.clabe='';
                this.fecha_peticion='';
                this.monto='';                             
                this.transaccion='';
                this.codigo='';
                this.autorizacion='';
                this.mensaje='';
                this.enviada='';
            },
            abrirModal(modelo, accion, data = []){
                switch(modelo){
                    case "cancelaspei":
                    {
                        switch(accion){                            
                            case 'ver':
                            {                                
                                this.modal=1;
                                this.tituloModal='Ver CancelaSpei';                                
                                this.cancelaspei_id=data['id'];
                                this.clabe = data['clabe'];
                                this.fecha_peticion = data['fecha_peticion'];
                                this.monto = data['monto'];
                                this.transaccion = data['transaccion'];
                                this.codigo = data['codigo'];
                                this.autorizacion = data['autorizacion'];
                                this.mensaje = data['mensaje'];
                                this.enviada = data['enviada'];
                                this.tipoAccion = 3;
                                window.scrollTo(0,0);
                                break;
                            }                                           
                        }
                    }
                }
            }            
        },
        watch: {
            tipo: {
                handler(val){
                    this.listarCancelaSpei(1,this.buscar,this.criterio);
                },
                deep: true
            }
        },
        mounted() {
            this.listarCancelaSpei(1,this.buscar,this.criterio);
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

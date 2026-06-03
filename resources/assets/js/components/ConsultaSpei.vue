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
                        <i class="fa fa-align-justify"></i> Consultas Spei
                        <!--<button type="button" @click="abrirModal('consultaspei','registrar')" class="btn btn-secondary">
                            <i class="icon-plus"></i>&nbsp;Nuevo
                        </button>--> &nbsp;
                        <button type="button" @click="descargarExportar()" class="btn btn-success btn-sm">
                            <i class="icon-cloud-download"></i>&nbsp;Exportar
                        </button> &nbsp;
                    </div>
                    <div class="card-body">
                        <div class="form-group row">
                            <div class="col-xl-6 col-lg-8 col-md-10 col-sm-12">
                                <div class="input-group">
                                    <select class="form-control col-md-3" v-model="criterio">
                                      <option value="reference">Clabe</option>
                                      <option value="ClientReference">Ref. Cliente</option>
                                      <option value="codigo">Código</option>
                                      <option value="mensaje">Mensaje</option>
                                    </select>
                                    <input type="text" v-model="buscar" @keyup.enter="listarConsultaSpei(1,buscar,criterio)" class="form-control" placeholder="Texto a buscar">
                                    <button type="submit" @click="listarConsultaSpei(1,buscar,criterio)" class="btn btn-primary"><i class="fa fa-search"></i> Buscar</button>
                                </div>
                            </div>
                        </div>
                        <table class="table table-bordered table-striped table-sm table-responsive">
                            <thead>
                                <tr>
                                    <th class="text-center">Opciones
                                        <select v-model="offset" @change="listarConsultaSpei(1,buscar,criterio)">
                                            <option value="10" selected>10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                        </select>
                                    </th>
                                    <th class="text-center">Folio</th>
                                    <th class="text-center">Fecha</th>
                                    <th class="text-center">Reference</th>                                    
                                    <th class="text-center">Código</th>
                                    <th class="text-center">Mensaje</th>                                    
                                    <th class="text-center">Parcial</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="consultaspei in arrayConsultaSpei" :key="consultaspei.id">
                                    <td class="text-center">
                                        <button type="button" @click="abrirModal('consultaspei','ver',consultaspei)" class="btn btn-success btn-sm">
                                          <i class="icon-eye"></i>
                                        </button> &nbsp;
                                        <!--<button type="button" class="btn btn-danger btn-sm" @click="eliminarConsultaSpei(consultaspei.id)">
                                            <i class="icon-trash"></i>
                                        </button>-->
                                    </td>
                                    <td v-text="consultaspei.id" class="text-center"></td>
                                    <td v-text="consultaspei.fecha" class="text-center"></td>
                                    <td v-text="consultaspei.reference" class="text-center"></td>
                                    <td v-text="consultaspei.codigo" class="text-center"></td>                       
                                    <td v-text="consultaspei.mensaje" class="text-center"></td>
                                    <td v-text="consultaspei.parcial" class="text-center"></td>
                                </tr>                                
                            </tbody>
                        </table>
                        <nav>
                            <ul class="pagination">
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
                                    <label class="col-md-3 form-control-label" for="text-input">Reference</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="reference" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                    </div>
                                </div>   
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Código</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="codigo" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                    </div>
                                </div>                               
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Mensaje</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="mensaje" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Parcial</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="parcial" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                    </div>
                                </div>
                                <div v-show="errorConsultaSpei" class="form-group row div-error">
                                    <div class="text-center text-error">
                                        <div v-for="error in errorMostrarMsjConsultaSpei" :key="error" v-text="error">
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="cerrarModal()">Cerrar</button>
                            <button type="button" v-if="tipoAccion==1" class="btn btn-primary" @click="registrarConsultaSpei()">Guardar</button>
                            <button type="button" v-if="tipoAccion==2" class="btn btn-primary" @click="actualizarConsultaSpei()">Actualizar</button>
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
                consultaspei_id: 0,
                fecha : null,
                reference : '',
                codigo : '',
                mensaje : '',
                parcial : '',                
                arrayConsultaSpei : [],                
                modal : 0,
                tituloModal : '',
                tipoAccion : 0,
                errorConsultaSpei : 0,
                errorMostrarMsjConsultaSpei : [],
                pagination : {
                    'total' : 0,
                    'current_page' : 0,
                    'per_page' : 0,
                    'last_page' : 0,
                    'from' : 0,
                    'to' : 0,
                },
                offset : 10,
                criterio : 'reference',
                buscar : '',
                loading: false
            }
        },
        computed:{
            isActived: function(){
                return this.pagination.current_page;
            },
            //Calcula los elementos de la paginación
            pagesNumber: function() {
                if(!this.pagination.to) {
                    return [];
                }
                
                var from = this.pagination.current_page - this.offset; 
                if(from < 1) {
                    from = 1;
                }

                var to = from + (this.offset * 2); 
                if(to >= this.pagination.last_page){
                    to = this.pagination.last_page;
                }  

                var pagesArray = [];
                while(from <= to) {
                    pagesArray.push(from);
                    from++;
                }
                return pagesArray;             

            }
        },
        methods : {
            listarConsultaSpei (page,buscar,criterio){
                let me=this;
                var url= '/consultaspei?page=' + page + '&buscar='+ buscar + '&criterio='+ criterio + '&offset='+ me.offset;
                axios.get(url).then(function (response) {
                    var consultaspei= response.data;
                    me.arrayConsultaSpei = consultaspei.consultaspei.data;
                    me.pagination= consultaspei.pagination;
                })
                .catch(function (error) {
                    console.log(error);
                });
            },
            descargarExportar (){
                let me = this;

                axios({
                    url: '/consultaspei/exportar',
                    meth: 'GET',
                    responseType: 'blob'
                    }).then(function (response) {                    
                        var fileURL = window.URL.createObjectURL(new Blob([response.data]));
                        var fileLink = document.createElement('a');
                        
                        fileLink.href = fileURL;
                        fileLink.setAttribute('download', 'consultaspei.xls');
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
                me.listarConsultaSpei(page,buscar,criterio);
            },            
            cerrarModal(){
                this.modal=0;
                this.tituloModal='';
                
                this.fecha=null;
                this.reference='';                
                this.codigo='';
                this.mensaje='';
                this.parcial='';
            },
            abrirModal(modelo, accion, data = []){
                switch(modelo){
                    case "consultaspei":
                    {
                        switch(accion){
                            case 'registrar':
                            {
                                this.modal = 1;                                
                                this.tituloModal = 'Registrar ConsultaSpei';                                
                                this.reference='';
                                this.codigo='';
                                this.mensaje='';
                                this.parcial='';
                                this.tipoAccion = 1;
                                break;
                            }
                            case 'actualizar':
                            {                                
                                this.modal=1;
                                this.tituloModal='Actualizar ConsultaSpei';                                
                                this.consultaspei_id=data['id'];
                                this.reference = data['reference'];
                                this.codigo = data['codigo'];
                                this.mensaje = data['mensaje'];
                                this.parcial = data['parcial'];
                                this.tipoAccion = 2;
                                window.scrollTo(0,0);
                                break;
                            } 
                            case 'ver':
                            {                                
                                this.modal=1;
                                this.tituloModal='Ver ConsultaSpei';                                
                                this.consultaspei_id=data['id'];
                                this.reference = data['reference'];
                                this.codigo = data['codigo'];
                                this.mensaje = data['mensaje'];
                                this.parcial = data['parcial'];
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
                    this.listarConsultaSpei(1,this.buscar,this.criterio);
                },
                deep: true
            }
        },
        mounted() {
            this.listarConsultaSpei(1,this.buscar,this.criterio);
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

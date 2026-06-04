<template>
            <main class="main">
            <!-- Breadcrumb -->
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/">Escritorio</a></li>
            </ol>
            <div class="container-fluid">
                <!-- Ejemplo de tabla Listado -->
                <div class="card">
                    <div class="card-header">
                        <i class="fa fa-align-justify"></i> Estados
                        <button type="button" @click="abrirModal('estado','registrar')" class="btn btn-secondary">
                            <i class="icon-plus"></i>&nbsp;Nuevo
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="form-group row cdc-list-toolbar">
                            <div class="col-xl-6 col-lg-8 col-md-10 col-sm-12">
                                <div class="input-group">
                                    <select class="form-control col-md-3" v-model="criterio">
                                      <option value="nombre">Nombre</option>
                                    </select>
                                    <input type="text" v-model="buscar" @keyup.enter="listarEstado(1,buscar,criterio)" class="form-control" placeholder="Texto a buscar">
                                    <button type="submit" @click="listarEstado(1,buscar,criterio)" class="btn btn-primary"><i class="fa fa-search"></i> Buscar</button>
                                </div>
                            </div>
                        </div>
                        <div class="cdc-table-shell">
                            <table class="table table-bordered table-striped table-sm cdc-responsive-table">
                                <thead>
                                    <tr>
                                        <th>Opciones
                                            <select v-model="offset" @change="listarEstado(1,buscar,criterio)">
                                                <option value="10" selected>10</option>
                                                <option value="25">25</option>
                                                <option value="50">50</option>
                                                <option value="100">100</option>
                                            </select>
                                        </th>
                                        <th>Nombre</th>
                                        <th>Status
                                            <select v-model="status" @change="listarEstado(1,buscar,criterio)">
                                                <option value="99" selected>Todos</option>
                                                <option value="0">Desactivados</option>
                                                <option value="1">Activos</option>
                                            </select>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="estado in arrayEstado" :key="estado.id">
                                        <td>
                                            <button type="button" @click="abrirModal('estado','actualizar',estado)" class="btn btn-warning btn-sm cdc-action-button" title="Editar estado" aria-label="Editar estado">
                                              <i class="icon-pencil"></i>
                                            </button> &nbsp;
                                            <template v-if="estado.condicion">
                                                <button type="button" class="btn btn-danger btn-sm cdc-action-button" @click="desactivarEstado(estado.id)" title="Desactivar estado" aria-label="Desactivar estado">
                                                    <i class="icon-trash"></i>
                                                </button>
                                            </template>
                                            <template v-else>
                                                <button type="button" class="btn btn-info btn-sm cdc-action-button" @click="activarEstado(estado.id)" title="Activar estado" aria-label="Activar estado">
                                                    <i class="icon-check"></i>
                                                </button>
                                            </template>
                                        </td>
                                        <td v-text="estado.nombre"></td>
                                        <td>
                                            <div v-if="estado.condicion">
                                                <span class="badge badge-success">Activo</span>
                                            </div>
                                            <div v-else>
                                                <span class="badge badge-danger">Desactivado</span>
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
                                    <label class="col-md-3 form-control-label" for="text-input">Nombre</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="nombre" class="form-control" placeholder="Nombre de Estado">
                                        
                                    </div>
                                </div>                                
                                <div v-show="errorEstado" class="form-group row div-error">
                                    <div class="text-center text-error">
                                        <div v-for="error in errorMostrarMsjEstado" :key="error" v-text="error">

                                        </div>
                                    </div>
                                </div>

                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="cerrarModal()">Cerrar</button>
                            <button type="button" v-if="tipoAccion==1" class="btn btn-primary" @click="registrarEstado()">Guardar</button>
                            <button type="button" v-if="tipoAccion==2" class="btn btn-primary" @click="actualizarEstado()">Actualizar</button>
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
        data (){
            return {
                estado_id: 0,
                nombre : '',
                arrayEstado : [],
                modal : 0,
                tituloModal : '',
                tipoAccion : 0,
                errorEstado : 0,
                errorMostrarMsjEstado : [],
                pagination : {
                    'total' : 0,
                    'current_page' : 0,
                    'per_page' : 0,
                    'last_page' : 0,
                    'from' : 0,
                    'to' : 0,
                },
                offset : 10,
                status : 99,
                criterio : 'nombre',
                buscar : ''
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
            listarEstado (page,buscar,criterio){
                let me=this;
                var url= '/estado?page=' + page + '&buscar='+ encodeURIComponent(buscar || '') + '&criterio='+ encodeURIComponent(criterio || 'nombre') + '&offset='+ me.offset + '&status='+ me.status;
                axios.get(url).then(function (response) {
                    var respuesta= response.data;
                    me.arrayEstado = respuesta.estados.data;
                    me.pagination= respuesta.pagination;
                })
                .catch(function (error) {
                    console.log(error);
                });
            },
            cambiarPagina(page,buscar,criterio){
                let me = this;
                //Actualiza la página actual
                me.pagination.current_page = page;
                //Envia la petición para visualizar la data de esa página
                me.listarEstado(page,buscar,criterio);
            },
            registrarEstado(){
                if (this.validarEstado()){
                    return;
                }
                
                let me = this;

                axios.post('/estado/registrar',{
                    'nombre': this.nombre
                }).then(function (response) {
                    me.cerrarModal();
                    me.listarEstado(1,me.buscar,me.criterio);
                }).catch(function (error) {
                    console.log(error);
                });
            },
            actualizarEstado(){
               if (this.validarEstado()){
                    return;
                }
                
                let me = this;

                axios.put('/estado/actualizar',{
                    'nombre': this.nombre,
                    'id': this.estado_id
                }).then(function (response) {
                    me.cerrarModal();
                    me.listarEstado(me.pagination.current_page || 1,me.buscar,me.criterio);
                }).catch(function (error) {
                    console.log(error);
                }); 
            },
            desactivarEstado(id){
               swal({
                title: 'Esta seguro de desactivar este Estado?',
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
                if (result.value) {
                    let me = this;

                    axios.put('/estado/desactivar',{
                        'id': id
                    }).then(function (response) {
                        me.listarEstado(me.pagination.current_page || 1,me.buscar,me.criterio);
                        swal(
                        'Desactivado!',
                        'El registro ha sido desactivado con éxito.',
                        'success'
                        )
                    }).catch(function (error) {
                        console.log(error);
                    });
                    
                    
                } else if (
                    // Read more about handling dismissals
                    result.dismiss === swal.DismissReason.cancel
                ) {
                    
                }
                }) 
            },
            activarEstado(id){
               swal({
                title: 'Esta seguro de activar este Estado?',
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
                if (result.value) {
                    let me = this;

                    axios.put('/estado/activar',{
                        'id': id
                    }).then(function (response) {
                        me.listarEstado(me.pagination.current_page || 1,me.buscar,me.criterio);
                        swal(
                        'Activado!',
                        'El registro ha sido activado con éxito.',
                        'success'
                        )
                    }).catch(function (error) {
                        console.log(error);
                    });
                    
                    
                } else if (
                    // Read more about handling dismissals
                    result.dismiss === swal.DismissReason.cancel
                ) {
                    
                }
                }) 
            },
            validarEstado(){
                this.errorEstado=0;
                this.errorMostrarMsjEstado =[];

                if (!this.nombre) this.errorMostrarMsjEstado.push("El nombre del Estado no puede estar vacío.");

                if (this.errorMostrarMsjEstado.length) this.errorEstado = 1;

                return this.errorEstado;
            },
            cerrarModal(){
                this.modal=0;
                this.tituloModal='';
                this.nombre='';
            },
            abrirModal(modelo, accion, data = []){
                switch(modelo){
                    case "estado":
                    {
                        switch(accion){
                            case 'registrar':
                            {
                                this.modal = 1;
                                this.tituloModal = 'Registrar Estado';
                                this.nombre= '';
                                this.tipoAccion = 1;
                                break;
                            }
                            case 'actualizar':
                            {
                                //console.log(data);
                                this.modal=1;
                                this.tituloModal='Actualizar Estado';
                                this.tipoAccion=2;
                                this.estado_id=data['id'];
                                this.nombre = data['nombre'];
                                window.scrollTo(0,0);
                                break;
                            }
                        }
                    }
                }
            }
        },
        mounted() {
            this.listarEstado(1,this.buscar,this.criterio);
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

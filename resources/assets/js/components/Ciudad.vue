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
                        <i class="fa fa-align-justify"></i> Ciudades
                        <button type="button" @click="abrirModal('ciudad','registrar')" class="btn btn-secondary">
                            <i class="icon-plus"></i>&nbsp;Nuevo
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="form-group row">
                            <div class="col-xl-6 col-lg-8 col-md-10 col-sm-12">
                                <div class="input-group">
                                    <select class="form-control col-md-3" v-model="criterio">
                                      <option value="nombre">Nombre</option>
                                      <option value="nombre_estado">Estado</option>
                                    </select>
                                    <input type="text" v-model="buscar" @keyup.enter="listarCiudad(1,buscar,criterio)" class="form-control" placeholder="Texto a buscar">
                                    <button type="submit" @click="listarCiudad(1,buscar,criterio)" class="btn btn-primary"><i class="fa fa-search"></i> Buscar</button>
                                </div>
                            </div>
                        </div>
                        <table class="table table-bordered table-striped table-sm table-responsive">
                            <thead>
                                <tr>
                                    <th>Opciones
                                        <select v-model="offset" @change="listarCiudad(1,buscar,criterio)">
                                            <option value="10" selected>10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                        </select>
                                    </th>
                                    <th>Nombre</th>
                                    <th>Estado</th>
                                    <th>Status
                                        <select v-model="status" @change="listarCiudad(1,buscar,criterio)">
                                            <option value="99" selected>Todos</option>
                                            <option value="0">Desactivados</option>
                                            <option value="1">Activos</option>
                                        </select> 
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="ciudad in arrayCiudad" :key="ciudad.id">
                                    <td>
                                        <button type="button" @click="abrirModal('ciudad','actualizar',ciudad)" class="btn btn-warning btn-sm">
                                          <i class="icon-pencil"></i>
                                        </button> &nbsp;
                                        <template v-if="ciudad.condicion">
                                            <button type="button" class="btn btn-danger btn-sm" @click="desactivarCiudad(ciudad.id)">
                                                <i class="icon-trash"></i>
                                            </button>
                                        </template>
                                        <template v-else>
                                            <button type="button" class="btn btn-info btn-sm" @click="activarCiudad(ciudad.id)">
                                                <i class="icon-check"></i>
                                            </button>
                                        </template>
                                    </td>
                                    <td v-text="ciudad.nombre"></td>
                                    <td v-text="ciudad.nombre_estado"></td>
                                    <td>
                                        <div v-if="ciudad.condicion">
                                            <span class="badge badge-success">Activo</span>
                                        </div>
                                        <div v-else>
                                            <span class="badge badge-danger">Desactivado</span>
                                        </div>
                                        
                                    </td>
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
                                    <label class="col-md-3 form-control-label" for="text-input">Nombre</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="nombre" class="form-control" placeholder="Nombre de Ciudad">
                                        
                                    </div>
                                </div>
                               <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Estado</label>
                                    <div class="col-md-9">
                                        <select class="form-control" v-model="idestado">
                                            <option value="0" disabled>Seleccione</option>
                                            <option v-for="estado in arrayEstado" :key="estado.id" :value="estado.id" v-text="estado.nombre"></option>
                                        </select>                                        
                                    </div>
                                </div>
                                <div v-show="errorCiudad" class="form-group row div-error">
                                    <div class="text-center text-error">
                                        <div v-for="error in errorMostrarMsjCiudad" :key="error" v-text="error">

                                        </div>
                                    </div>
                                </div>

                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="cerrarModal()">Cerrar</button>
                            <button type="button" v-if="tipoAccion==1" class="btn btn-primary" @click="registrarCiudad()">Guardar</button>
                            <button type="button" v-if="tipoAccion==2" class="btn btn-primary" @click="actualizarCiudad()">Actualizar</button>
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
                ciudad_id: 0,
                nombre : '',
                idestado: 0,
                nombre_estado : '',
                arrayCiudad : [],
                modal : 0,
                tituloModal : '',
                tipoAccion : 0,
                errorCiudad : 0,
                errorMostrarMsjCiudad : [],
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
                buscar : '',
                arrayEstado :[]
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
            listarCiudad (page,buscar,criterio){
                let me=this;
                var url= '/ciudad?page=' + page + '&buscar='+ buscar + '&criterio='+ criterio + '&offset='+ me.offset;
                axios.get(url).then(function (response) {
                    var respuesta= response.data;
                    me.arrayCiudad = respuesta.ciudades.data;
                    me.pagination= respuesta.pagination;
                })
                .catch(function (error) {
                    console.log(error);
                });
            },
            selectEstado(){
                let me=this;
                var url= '/estado/selectEstado';
                axios.get(url).then(function (response) {
                    //console.log(response);
                    var respuesta= response.data;
                    me.arrayEstado = respuesta.estados;
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
                me.listarCiudad(page,buscar,criterio);
            },                   
            registrarCiudad(){
                if (this.validarCiudad()){
                    return;
                }
                
                let me = this;

                axios.post('/ciudad/registrar',{
                    'nombre': this.nombre,
                    'idestado': this.idestado
                }).then(function (response) {
                    me.cerrarModal();
                    me.listarCiudad(1,'','nombre');
                }).catch(function (error) {
                    console.log(error);
                        swal(
                        'Error!',
                        'Error al realizar el registro.',
                        'error'
                        )                       
                });
            },
            actualizarCiudad(){
               if (this.validarCiudad()){
                    return;
                }
                
                let me = this;

                axios.put('/ciudad/actualizar',{
                    'nombre': this.nombre,
                    'idestado': this.idestado,
                    'id': this.ciudad_id
                }).then(function (response) {
                    me.cerrarModal();
                    me.listarCiudad(1,'','nombre');
                }).catch(function (error) {
                    console.log(error);
                        swal(
                        'Error!',
                        'Error al actualizar el registro.',
                        'error'
                        )                       
                }); 
            },
            desactivarCiudad(id){
               swal({
                title: 'Esta seguro de desactivar esta ciudad?',
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

                    axios.put('/ciudad/desactivar',{
                        'id': id
                    }).then(function (response) {
                        me.listarCiudad(1,'','nombre');
                        swal(
                        'Desactivado!',
                        'El registro ha sido desactivado con éxito.',
                        'success'
                        )
                    }).catch(function (error) {
                        console.log(error);
                        swal(
                        'Error!',
                        'Error al desactivar el registro.',
                        'error'
                        )                           
                    });
                    
                    
                } else if (
                    // Read more about handling dismissals
                    result.dismiss === swal.DismissReason.cancel
                ) {
                    
                }
                }) 
            },
            activarCiudad(id){
               swal({
                title: 'Esta seguro de activar esta ciudad?',
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

                    axios.put('/ciudad/activar',{
                        'id': id
                    }).then(function (response) {
                        me.listarCiudad(1,'','nombre');
                        swal(
                        'Activado!',
                        'El registro ha sido activado con éxito.',
                        'success'
                        )
                    }).catch(function (error) {
                        console.log(error);
                        swal(
                        'Error!',
                        'Error al activar el registro.',
                        'error'
                        )                           
                    });
                    
                    
                } else if (
                    // Read more about handling dismissals
                    result.dismiss === swal.DismissReason.cancel
                ) {
                    
                }
                }) 
            },
            validarCiudad(){
                this.errorCiudad=0;
                this.errorMostrarMsjCiudad =[];

                if (!this.nombre) this.errorMostrarMsjCiudad.push("El nombre de la ciudad no puede estar vacío.");

                if (this.errorMostrarMsjCiudad.length) this.errorCiudad = 1;

                return this.errorCiudad;
            },
            cerrarModal(){
                this.modal=0;
                this.tituloModal='';
                this.nombre='';
                this.idestado=0;
                this.nombre_estado='';
            },
            abrirModal(modelo, accion, data = []){
                switch(modelo){
                    case "ciudad":
                    {
                        switch(accion){
                            case 'registrar':
                            {
                                this.modal = 1;
                                this.tituloModal = 'Registrar Ciudad';
                                this.nombre= '';
                                this.idestado = 0;
                                this.nombre_estado = '';
                                this.tipoAccion = 1;
                                break;
                            }
                            case 'actualizar':
                            {
                                //console.log(data);
                                this.modal=1;
                                this.tituloModal='Actualizar Ciudad';
                                this.tipoAccion=2;
                                this.ciudad_id=data['id'];
                                this.nombre = data['nombre'];
                                this.idestado= data['idestado'];
                                window.scrollTo(0,0);
                                break;
                            }
                        }
                    }
                }
                this.selectEstado();
            }
        },
        mounted() {
            this.listarCiudad(1,this.buscar,this.criterio);
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

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
                        <i class="fa fa-align-justify"></i> Clientes
                        <button type="button" @click="abrirModal('persona','registrar')" class="btn btn-secondary">
                            <i class="fa fa-plus-circle"></i>&nbsp;Nuevo
                        </button> &nbsp;
                        <button type="button" @click="descargarExportar()" class="btn btn-success btn-sm">
                            <i class="fa fa-cloud-download"></i>&nbsp;Exportar
                        </button> &nbsp;
                    </div>
                    <div class="card-body">
                        <div class="form-group row">
                            <div class="col-xl-6 col-lg-8 col-md-10 col-sm-12">
                                <div class="input-group">
                                    <select class="form-control col-md-3" v-model="criterio">
                                      <option value="nombre">Nombre</option>
                                      <option value="razon_social">Razón Social</option>
                                      <option value="rfc">RFC</option>
                                      <option value="email">Email</option>
                                      <option value="telefono">Teléfono</option>
                                    </select>
                                    <input type="text" v-model="buscar" @keyup.enter="listarPersona(1,buscar,criterio)" class="form-control" placeholder="Texto a buscar">
                                    <button type="submit" @click="listarPersona(1,buscar,criterio)" class="btn btn-primary"><i class="fa fa-search"></i> Buscar</button>
                                </div>
                            </div>
                        </div>
                        <table class="table table-bordered table-striped table-sm table-responsive">
                            <thead>
                                <tr>
                                    <th>Opciones
                                        <select v-model="offset" @change="listarPersona(1,buscar,criterio)">
                                                <option value="10" selected>10</option>
                                                <option value="25">25</option>
                                                <option value="50">50</option>
                                                <option value="100">100</option>
                                            </select>                                        
                                    </th>
                                    <th>Folio</th>
                                    <th>Nombre</th>
                                    <th>RFC</th>
                                    <th>Dirección</th>
                                    <th>Teléfono</th>
                                    <th>Email</th>
                                    
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="persona in arrayPersona" :key="persona.id">
                                    <td>
                                        <button type="button" @click="abrirModal('persona','actualizar',persona)" class="btn btn-warning btn-sm">
                                          <i class="fa fa-pencil"></i>
                                        </button>
                                        <button type="button" @click="abrirModal('persona','ver',persona)" class="btn btn-warning btn-sm">
                                          <i class="fa fa-folder-open"></i>
                                        </button>
                                    </td>
                                    <td v-text="persona.num_documento"></td>
                                    <td v-text="persona.nombre"></td>
                                    <td v-text="persona.rfc"></td>
                                    <td v-text="persona.direccion"></td>
                                    <td v-text="persona.telefono"></td>
                                    <td v-text="persona.email"></td>                                    
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
                                <template v-if="(tipoAccion == 2 || tipoAccion == 3)">  
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Número</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="num_documento" class="form-control" placeholder="1" readonly>                                        
                                        </div>
                                    </div>
                                </template>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Nombre (*)</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="nombre" class="form-control" placeholder="Nombre comercial">                                        
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Razón Social (*)</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="razon_social" class="form-control" placeholder="Razón Social">                                        
                                    </div>
                                </div>                                
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">RFC (*)</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="rfc" class="form-control" placeholder="RFC">                                        
                                    </div>
                                </div>             
                                <!-- Template del registro o la actualización -->
                                <template v-if="tipoAccion==1 || tipoAccion == 2">                  
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="email-input">Dirección</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="direccion" class="form-control" placeholder="Dirección">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Estados *</label>
                                    <div class="col-md-9">
                                        <select class="form-control" v-model="idestado">
                                            <option value="0" disabled>Seleccione</option>
                                            <option v-for="estado in arrayEstado" :key="estado.id" :value="estado.id" v-text="estado.nombre"></option>
                                        </select>                                        
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Ciudades *</label>
                                    <div class="col-md-9">
                                        <select class="form-control" v-model="idciudad">
                                            <option value="0" disabled>Seleccione</option>
                                            <option v-for="ciudad in arrayCiudad" :key="ciudad.id" :value="ciudad.id" v-text="ciudad.nombre"></option>
                                        </select>                                        
                                    </div>
                                </div>                                                     
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="email-input">Teléfono</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="telefono" class="form-control" placeholder="Teléfono">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="email-input">Email</label>
                                    <div class="col-md-9">
                                        <input type="email" v-model="email" class="form-control" placeholder="Email">
                                    </div>
                                </div>
                                <template v-if="tipoAccion==4">
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="email-input">Contacto</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="contacto" class="form-control" placeholder="Nombre del contacto">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="email-input">Teléfono de contacto</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="telefono_contacto" class="form-control" placeholder="Teléfono del contacto">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="email-input">Email de contacto</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="email_contacto" class="form-control" placeholder="Email del contacto">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Forma de pago</label>
                                    <div class="col-md-9">
                                        <select v-model="forma_pago" class="form-control">
                                            <option value="0" disabled>Seleccione forma de pago</option>
                                            <option value="1">Crédito</option>
                                            <option value="2">Cheque Postfechado</option>
                                            <option value="3">Contado</option>
                                        </select>                                    
                                    </div>
                                </div> 
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="email-input">Plazo</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="plazo" class="form-control" placeholder="Plazo para pago">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Régimen Fiscal</label>
                                    <div class="col-md-9">
                                        <select v-model="regimen" class="form-control">
                                            <option value="0">Seleccione un régimen fiscal</option>
                                            <option value="601">601 - General de Ley Personas Morales</option>
                                            <option value="603">603 - Personas Morales con Fines no Lucrativos</option>                                            
                                            <option value="605">605 - Sueldos y Salarios e Ingresos Asimilados a Salarios</option>
                                            <option value="606">606 - Arrendamiento</option>
                                            <option value="607">607 - Régimen de Enajenación o Adquisición de Bienes</option>
                                            <option value="608">608 - Demás ingresos</option>
                                            <option value="610">610 - Residentes en el Extranjero sin Establecimiento Permanente en México</option>
                                            <option value="611">611 - Ingresos por Dividendos (socios y accionistas)</option>
                                            <option value="612">612 - Personas Físicas con Actividades Empresariales y Profesionales</option>
                                            <option value="614">614 - Ingresos por intereses</option>
                                            <option value="615">615 - Régimen de los ingresos por obtención de premios</option>
                                            <option value="616">616 - Sin obligaciones fiscales</option>
                                            <option value="620">620 - Sociedades Cooperativas de Producción que optan por diferir sus ingresos</option>
                                            <option value="621">621 - Incorporación Fiscal</option>                                            
                                            <option value="622">622 - Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras</option>
                                            <option value="623">623 - Opcional para Grupos de Sociedades</option>
                                            <option value="624">624 - Coordinados</option>
                                            <option value="625">625 - Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas</option>
                                            <option value="626">626 - Régimen Simplificado de Confianza</option>
                                            <option value="628">628 - Hidrocarburos</option>
                                            <option value="629">629 - De los Regímenes Fiscales Preferentes y de las Empresas Multinacionales</option>
                                            <option value="630">630 - Enajenación de acciones en bolsa de valores</option>
                                        </select>                                    
                                    </div>
                                </div> 
                                <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Banco</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="banco" class="form-control" placeholder="Banco">
                                        </div>
                                    </div>                                   
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">No. de Cuenta</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="cuenta" class="form-control" placeholder="No. de Cuenta">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">CLABE</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="clabe" class="form-control" placeholder="CLABE">
                                        </div>
                                    </div> 
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Sucursal Bancaria</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="cuenta_sucursal" class="form-control" placeholder="Sucursal Bancaria">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Ciudad de la cuenta</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="cuenta_ciudad" class="form-control" placeholder="Ciudad de la cuenta">
                                        </div>
                                    </div> 
                                    </template>
                                </template>
                                <!-- Termina template del registro o la actualización -->                                                                                                                                                      
                                
                                <!-- Template de los archivos -->
                                <template v-if="tipoAccion==3">
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Adjuntar documento</label>
                                        <div class="col-md-9">
                                            <input type="file" @change="selectFile" class="form-control" placeholder="Seleccione el archivo..." id="fileupload" ref="fileupload">                                            
                                        </div>
                                    </div>
                                    <table class="table table-bordered table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>Opciones</th>
                                                <th>Nombre</th>
                                                <th>Extensión</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="archivo in arrayArchivo" :key="archivo.id">
                                                <td>
                                                    <button type="button" @click="descargarArchivo(archivo.id, archivo.nombre)" class="btn btn-success btn-sm">
                                                    <i class="fa fa-cloud-download"></i>
                                                    </button>
                                                    <button type="button" @click="eliminarArchivo(archivo.id)" class="btn btn-danger btn-sm">
                                                    <i class="fa fa-trash"></i>
                                                    </button>                                        
                                                </td>
                                                <td v-text="archivo.nombre"></td>
                                                <td v-text="archivo.extension"></td>                                 
                                            </tr>                                
                                        </tbody>
                                    </table>                                
                                </template>
                                <!-- Termina template de los archivos -->

                                <div v-show="errorPersona" class="form-group row div-error">
                                    <div class="text-center text-error">
                                        <div v-for="error in errorMostrarMsjPersona" :key="error" v-text="error">

                                        </div>
                                    </div>
                                </div>

                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="cerrarModal()">Cerrar</button>
                            <button type="button" v-if="tipoAccion==1" class="btn btn-primary" @click="registrarPersona()">Guardar</button>
                            <button type="button" v-if="tipoAccion==2" class="btn btn-primary" @click="actualizarPersona()">Actualizar</button>
                            <button type="button" v-if="tipoAccion==3" class="btn btn-primary" @click="subirArchivo()">Subir Archivo</button>
                            <button type="button" v-if="tipoAccion==4" class="btn btn-primary" @click="registrarCuenta()">Guardar Cuenta</button>
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
                persona_id: 0,
                nombre : '',
                tipo_documento : 'CLIENTE',
                num_documento : '',
                direccion : '',
                idciudad : 0,
                idestado : 0,                
                telefono : '',
                email : '',
                contacto : '',
                telefono_contacto : '',
                email_contacto : '',
                rfc: '',
                razon_social: '',
                banco: '',         
                cuenta : '',
                clabe : '',         
                cuenta_sucursal : '',
                cuenta_ciudad : '',
                forma_pago: 0,
                plazo:0,
                regimen:'',
                arrayPersona : [],
                arrayEstado : [],
                arrayCiudad : [],
                archivo : null,
                arrayArchivo : [],                
                modal : 0,
                tituloModal : '',
                tipoAccion : 0,
                errorPersona : 0,
                errorMostrarMsjPersona : [],
                pagination : {
                    'total' : 0,
                    'current_page' : 0,
                    'per_page' : 0,
                    'last_page' : 0,
                    'from' : 0,
                    'to' : 0,
                },
                offset : 10,
                criterio : 'nombre',
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
            listarPersona (page,buscar,criterio){
                let me=this;
                var url= '/cliente?page=' + page + '&buscar='+ buscar + '&criterio='+ criterio + '&offset='+ me.offset;
                axios.get(url).then(function (response) {
                    var respuesta= response.data;
                    me.arrayPersona = respuesta.personas.data;
                    me.pagination= respuesta.pagination;
                })
                .catch(function (error) {
                    console.log(error);
                });
            },
            descargarExportar (){
                let me = this;

                axios({
                    url: '/cliente/exportar',
                    meth: 'GET',
                    responseType: 'blob'
                    }).then(function (response) {                    
                        var fileURL = window.URL.createObjectURL(new Blob([response.data]));
                        var fileLink = document.createElement('a');
                        
                        fileLink.href = fileURL;
                        fileLink.setAttribute('download', 'clientes.xls');
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
            listarArchivo (idpersona){
                let me=this;
                var url= '/archivo?idpersona=' + idpersona;
                axios.get(url).then(function (response) {
                    var respuesta= response.data;
                    me.arrayArchivo = respuesta.archivos.data;
                })
                .catch(function (error) {
                    console.log(error);
                });
            },                      
            selectCiudad(){
                let me=this;
                var url= '/ciudad/selectCiudad';
                axios.get(url).then(function (response) {
                    //console.log(response);
                    var respuesta= response.data;
                    me.arrayCiudad = respuesta.ciudades;
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
            selectFile(event) {
                // `files` is always an array because the file input may be in multiple mode
                this.archivo = event.target.files[0];
            },
            cambiarPagina(page,buscar,criterio){
                let me = this;
                //Actualiza la página actual
                me.pagination.current_page = page;
                //Envia la petición para visualizar la data de esa página
                me.listarPersona(page,buscar,criterio);
            },
            registrarPersona(){
                if (this.validarPersona()){                    
                    return;
                }
                this.loading =  true;
                let me = this;

                axios.post('/cliente/registrar',{
                    'nombre': this.nombre,
                    'tipo_documento': this.tipo_documento,                    
                    'direccion' : this.direccion,
                    'idciudad': this.idciudad,
                    'telefono' : this.telefono,
                    'email' : this.email,
                    'contacto': this.contacto,
                    'telefono_contacto': this.telefono_contacto,
                    'email_contacto': this.email_contacto,
                    'rfc' : this.rfc,
                    'razon_social' : this.razon_social,
                    'forma_pago' : this.forma_pago,
                    'plazo' : this.plazo,
                    'regimen' : this.regimen,
                    'banco' : this.banco,
                    'cuenta' : this.cuenta,
                    'clabe' : this.clabe,
                    'cuenta_sucursal' : this.cuenta_sucursal,
                    'cuenta_ciudad' : this.cuenta_ciudad                    
                }).then(function (response) {
                    me.cerrarModal();
                    me.listarPersona(1,'','nombre');
                }).catch(function (error) {
                    console.log(error);
                        swal(
                        'Error!',
                        'Error al realizar el registro.',
                        'error'
                        )                      
                }).finally(() => {
                        this.loading =  false;
                });
            },
            actualizarPersona(){
               if (this.validarPersona()){                    
                    return;
                }
                this.loading =  true;
                let me = this;

                axios.put('/cliente/actualizar',{
                    'nombre': this.nombre,
                    'tipo_documento': this.tipo_documento,                    
                    'direccion' : this.direccion,
                    'idciudad': this.idciudad,                    
                    'telefono' : this.telefono,
                    'email' : this.email,
                    'contacto': this.contacto,
                    'telefono_contacto': this.telefono_contacto,
                    'email_contacto': this.email_contacto,
                    'rfc' : this.rfc,
                    'razon_social' : this.razon_social,
                    'forma_pago' : this.forma_pago,
                    'plazo' : this.plazo,
                    'regimen' : this.regimen,
                    'banco' : this.banco,
                    'cuenta' : this.cuenta,
                    'clabe' : this.clabe,
                    'cuenta_sucursal' : this.cuenta_sucursal,
                    'cuenta_ciudad' : this.cuenta_ciudad,
                    'id': this.persona_id
                }).then(function (response) {
                    me.cerrarModal();
                    me.listarPersona(1,'','nombre');
                }).catch(function (error) {
                    console.log(error);
                        swal(
                        'Error!',
                        'Error al actualizar el registro.',
                        'error'
                        )                      
                }).finally(() => {
                        this.loading =  false;
                });
            },            
            subirArchivo(){       
                if (this.validarArchivo()){
                    return;
                }       
                
                let me = this;

                let data = new FormData();
                data.append('archivo', this.archivo);
                data.append('idpersona', this.persona_id);

                axios.post('/archivo/registrar',data).then(function (response) {                    
                    console.log("Se guardo.");
                    me.listarArchivo(me.persona_id);
                }).catch(function (error) {
                    console.log(error);
                        swal(
                        'Error!',
                        'Error al subir el archivo.',
                        'error'
                        )                      
                }); 
            },
            descargarArchivo(id, name){              
                let me = this;

                axios({
                    url: '/archivo/descargar?id=' + id,
                    meth: 'GET',
                    responseType: 'blob'
                    }).then(function (response) {                    
                        var fileURL = window.URL.createObjectURL(new Blob([response.data]));
                        var fileLink = document.createElement('a');
                        
                        fileLink.href = fileURL;
                        fileLink.setAttribute('download', name);
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
            eliminarArchivo(id){              
                let me = this;
                swal({
                title: 'Esta seguro de eliminar este archivo?',
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
                    axios.put('/archivo/eliminar',{                    
                        'id': id
                    }).then(function (response) {                    
                        me.listarArchivo(me.persona_id);
                    }).catch(function (error) {
                        console.log(error);
                        swal(
                        'Error!',
                        'Error al eliminar el archivo.',
                        'error'
                        )                          
                    }); 
                })
            },
                                    
            validarPersona(){
                this.errorPersona=0;
                this.errorMostrarMsjPersona =[];

                if (!this.nombre) this.errorMostrarMsjPersona.push("El nombre del cliente no puede estar vacío.");
                if (!this.rfc) this.errorMostrarMsjPersona.push("El RFC del cliente no puede estar vacío.");
                if (!this.razon_social) this.errorMostrarMsjPersona.push("La razón social del cliente no puede estar vacío.");

                //if (!this.banco) this.errorMostrarMsjPersona.push("El banco de la cuenta no puede estar vacío.");
                //if (!this.cuenta) this.errorMostrarMsjPersona.push("La cuenta no puede estar vacía.");
                //if (!this.clabe) this.errorMostrarMsjPersona.push("La CLABE no puede estar vacía.");
                //if (!this.cuenta_sucursal) this.errorMostrarMsjPersona.push("La sucursal de la cuetna no puede estar vacía.");
                //if (!this.cuenta_ciudad) this.errorMostrarMsjPersona.push("La ciudad de la cuenta no puede estar vacía.");

                if (this.errorMostrarMsjPersona.length) this.errorPersona = 1;

                return this.errorPersona;
            },
            validarArchivo(){
                this.errorPersona=0;
                this.errorMostrarMsjPersona =[];

                if (!this.archivo) this.errorMostrarMsjPersona.push("Debe seleccionar un archivo.");

                if (this.errorMostrarMsjPersona.length) this.errorPersona = 1;

                return this.errorPersona;
            },                       
            cerrarModal(){
                this.modal=0;
                this.tituloModal='';
                this.nombre='';
                this.tipo_documento='CLIENTE';
                this.num_documento='';
                this.direccion='';
                this.idciudad=0;
                this.idestado=0;
                this.telefono='';
                this.email='';
                this.contacto='';
                this.email_contacto='';
                this.telefono_contacto='';
                this.errorPersona=0;
                this.archivo=null;
                this.arrayArchivo=[];                
                this.rfc = '';
                this.razon_social = '';
                this.banco = '';
                this.cuenta = '';
                this.clabe = '';
                this.cuenta_sucursal = '';
                this.cuenta_ciudad = '';
                this.forma_pago = 0;
                this.plazo = 0;
                this.regimen = '0';
            },
            abrirModal(modelo, accion, data = []){
                switch(modelo){
                    case "persona":
                    {
                        switch(accion){
                            case 'registrar':
                            {
                                this.modal = 1;
                                this.tituloModal = 'Registrar Cliente';
                                this.nombre= '';
                                this.tipo_documento='CLIENTE';
                                this.num_documento='';
                                this.direccion='';
                                this.idciudad=0;
                                this.idestado=0;
                                this.telefono='';
                                this.email='';
                                this.contacto='';
                                this.email_contacto='';
                                this.telefono_contacto='';
                                this.archivo=null;
                                this.arrayArchivo=[];
                                this.rfc = '';
                                this.razon_social = '';
                                this.forma_pago = 0;
                                this.plazo = 0;
                                this.regimen = '0'; 
                                this.banco = '';
                                this.cuenta = '';
                                this.clabe = '';
                                this.cuenta_sucursal = '';
                                this.cuenta_ciudad = '';
                                this.tipoAccion = 1;                                
                                break;
                            }
                            case 'actualizar':
                            {
                                //console.log(data);
                                this.modal=1;
                                this.tituloModal='Actualizar Cliente';                                
                                this.persona_id=data['id'];
                                this.nombre = data['nombre'];
                                this.tipo_documento = data['tipo_documento'];
                                this.num_documento = data['num_documento'];
                                this.direccion = data['direccion'];
                                this.idciudad = data['idciudad'];
                                this.telefono = data['telefono'];
                                this.email = data['email'];
                                this.contacto = data['contacto'];
                                this.telefono_contacto = data['telefono_contacto'];
                                this.idestado = data['idestado'];
                                this.idciudad = data['idciudad'];
                                this.rfc = data['rfc'];
                                this.razon_social = data['razon_social']                                
                                this.forma_pago = data['forma_pago'];                                
                                this.plazo = data['plazo'];
                                this.regimen = data['regimen'];
                                this.banco = data['banco'];
                                this.cuenta = data['cuenta'];
                                this.clabe = data['clabe'];
                                this.cuenta_sucursal = data['cuenta_sucursal'];
                                this.cuenta_ciudad = data['cuenta_ciudad'];
                                this.archivo=null;
                                this.arrayArchivo=[];
                                this.tipoAccion=2;
                                window.scrollTo(0,0);
                                break;
                            }
                            case 'ver':
                            {
                                //console.log(data);
                                this.modal=1;
                                this.tituloModal='Archivos Cliente';                                
                                this.persona_id=data['id'];
                                this.nombre = data['nombre'];
                                this.tipo_documento = data['tipo_documento'];
                                this.num_documento = data['num_documento'];
                                this.rfc = data['rfc'];
                                this.razon_social = data['razon_social'];
                                this.archivo=null;      
                                this.listarArchivo(data['id']);
                                this.tipoAccion=3;
                                window.scrollTo(0,0);
                                break;
                            }                                                      
                        }
                    }
                }
                this.selectCiudad();
                this.selectEstado();
            }
        },
        mounted() {
            this.listarPersona(1,this.buscar,this.criterio);
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

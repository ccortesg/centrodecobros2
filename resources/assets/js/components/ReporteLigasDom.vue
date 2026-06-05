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
                        <i class="fa fa-align-justify"></i> 
                        <template v-if="tipo==1">
                            Reporte Ingresos por Ligas de Pago
                        </template>
                        <template v-else-if="tipo==2">
                            Reporte de Domiciliación
                        </template>
                    </div>
                    <!-- Listado-->
                    
                    <div class="card-body">
                        <div class="form-group row">
                            <div class="col-md-12">                                
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="">Fecha Inicio</label>
                                        <input type="date" v-model="fechaInicio" class="form-control" @change="selectFechaInicio()">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="">Fecha Fin</label>
                                        <input type="date" v-model="fechaFin" class="form-control" @change="selectFechaFin()">
                                    </div>
                                </div>    
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="">Cliente</label>
                                        <select class="form-control" v-model="idcliente">
                                            <option value="0" selected>Todos</option>
                                            <option v-for="cliente in arrayCliente" :key="cliente.id" :value="cliente.id" v-text="cliente.razon_social"></option>
                                        </select>  
                                    </div>
                                </div>   
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <button type="button" @click="exportarTransacciones()" class="btn btn-success btn-sm">
                                            <i class="fa fa-cloud-download"></i>&nbsp;Exportar
                                        </button> &nbsp;
                                        <button type="submit" @click="listarTransacciones()" class="btn btn-primary">
                                            <i class="fa fa-file"></i> Listar
                                        </button>
                                    </div>
                                </div>                                
                            </div>
                        </div>                        
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-striped table-sm table-responsive">
                            <thead>
                                <tr>                                    
                                    <th class="text-center">Folio</th>
                                    <th class="text-center">Fecha</th>
                                    <th class="text-center">Cliente</th>
                                    <th class="text-center">Forma de Pago</th>
                                    <th class="text-center">Descripción</th>
                                    <th class="text-center">Referencia</th>
                                    <th class="text-center">Monto</th>
                                    <th class="text-center">Status</th>
                                    
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="transaccion in arrayTransaccion" :key="transaccion.id">
                                    <td v-text="transaccion.folio" class="text-center"></td>
                                    <td v-text="transaccion.fecha" class="text-center"></td>
                                    <td v-text="transaccion.razon_social" class="text-center"></td>
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
                                    <td v-text="transaccion.Description" class="text-center"></td>
                                    <td v-text="transaccion.ClientReference" class="text-center"></td>
                                    <td class="text-center">{{ $formatCurrency(transaccion.Amount / 100) }}</td>
                                    <td class="text-center">
                                        <template v-if="transaccion.condicion=='1'">
                                            <span class="badge badge-success">Activo</span>
                                        </template>    
                                        <template v-if="transaccion.condicion=='2'">
                                            <span class="badge badge-danger">Cancelado</span>
                                        </template>
                                    </td>
                                </tr>
                                <tr style="background-color: #CEECF5;">
                                    <td colspan="7" align="right"><strong>Total Neto:</strong></td>
                                    <td  class="text-center">{{ $formatCurrency(calcularTotal) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Fin ejemplo de tabla Listado -->
            </div>            
        </main>
</template>

<script>
    export default {
        props: ['tipo'],
        data (){
            return {
                arrayTransaccion : [],
                arrayCliente : [],                
                idcliente : 0,
                fechaInicio : null,
                fechaFin : null
            }
        },
        computed:{            
            calcularTotal: function(){
                var resultado=0.0;
                for(var i=0;i<this.arrayTransaccion.length;i++){
                    resultado=resultado+(this.arrayTransaccion[i].Amount/100)
                }
                return resultado;
            }
        },
        methods : {
            exportarTransacciones (){
                let me = this;

                axios({
                    url: '/transaccion/exportarTransacciones?idcliente=' + me.idcliente + '&fechaInicio=' + me.fechaInicio + '&fechaFin=' + me.fechaFin + '&tipo='+ me.tipo,
                    meth: 'GET',
                    responseType: 'blob'
                    }).then(function (response) {                    
                        var fileURL = window.URL.createObjectURL(new Blob([response.data]));
                        var fileLink = document.createElement('a');
                        
                        fileLink.href = fileURL;
                        fileLink.setAttribute('download', 'reporteTransaccionesDom.xlsx');
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
            listarTransacciones (){
                let me=this;
                var url= '/transaccion/reporteTransacciones?idcliente=' + me.idcliente + '&fechaInicio=' + me.fechaInicio + '&fechaFin=' + me.fechaFin + '&tipo='+ me.tipo;
                axios.get(url).then(function (response) {
                    var respuesta= response.data;
                    me.arrayTransaccion = respuesta.transacciones;                    
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
            selectFechaInicio(){
                let me=this;
                
                if(me.fechaFin == null) me.fechaFin = me.fechaInicio;

                if(me.fechaInicio > me.fechaFin){
                    me.fechaFin = me.fechaInicio;
                }
            }, 
            selectFechaFin(){
                let me=this;

                if(me.fechaInicio == null) me.fechaInicio = me.fechaFin;

                if(me.fechaFin < me.fechaInicio){
                    me.fechaInicio = me.fechaFin;
                }
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
        },
        mounted() {            
            this.selectCliente();            
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
    @media (min-width: 600px) {
        .btnagregar {
            margin-top: 2rem;
        }
    }

</style>

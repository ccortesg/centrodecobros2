<template>
<main class="main">
    <!-- Breadcrumb -->
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Escritorio</a></li>
    </ol>
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                
            </div>
            <div class="car-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card card-chart">
                            <div class="card-header">
                                <h4>Liga de pago</h4>
                            </div>
                            <div class="card-content">
                                <div class="ct-chart">
                                    <canvas id="transacciones">                                                
                                    </canvas>
                                </div>
                            </div>
                            <div class="card-footer">
                                <p>Ingreso por ligas de pago.</p>
                            </div>
                        </div>
                    </div>  
                    <div class="col-md-6">
                        <div class="card card-chart">
                            <div class="card-header">
                                <h4>Domiciliación</h4>
                            </div>
                            <div class="card-content">
                                <div class="ct-chart">
                                    <canvas id="importes">                                                
                                    </canvas>
                                </div>
                            </div>
                            <div class="card-footer">
                                <p>Ingreso por cargos por domiciliación.</p>
                            </div>
                        </div>
                    </div>                                        
                </div>
            </div>
        </div>
    </div>

</main>
</template>
<script>
    export default {
        data (){
            return {
                varTransaccion:null,
                charTransaccion:null,
                transacciones:[],
                varTotalTransaccion:[],
                varMesTransaccion:[], 
                varImporte:null,
                charImporte:null,
                importes:[],
                varTotalImporte:[],
                varMesImporte:[],
                meses : ["ENERO", "FEBRERO", "MARZO", "ABRIL", "MAYO", "JUNIO", "JULIO", "AGOSTO", "SEPTIEMBRE", "OCTUBRE", "NOVIEMBRE", "DICIEMBRE"],
            }
        },
        methods : {
            getTransacciones(){
                let me=this;
                var url= '/dashboard';
                axios.get(url).then(function (response) {
                    var respuesta= response.data;
                    me.transacciones = respuesta.transacciones;
                    //cargamos los datos del chart
                    me.loadTransacciones();
                })
                .catch(function (error) {
                    console.log(error);
                });
            },
            loadTransacciones(){
                let me=this;
                me.transacciones.map(function(x){
                    me.varMesTransaccion.push(me.meses[(x.mes-1)]);
                    me.varTotalTransaccion.push(x.total);
                });
                me.varTransaccion=document.getElementById('transacciones').getContext('2d');

                me.charTransaccion = new Chart(me.varTransaccion, {
                    type: 'bar',
                    data: {
                        labels: me.varMesTransaccion,
                        datasets: [{
                            label: 'Ingreso',
                            data: me.varTotalTransaccion,
                            backgroundColor: '#ffc107',
                            borderColor: 'rgba(255, 99, 132, 0.2)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        scales: {
                            yAxes: [{
                                ticks: {
                                    beginAtZero:true
                                }
                            }]
                        }
                    }
                });
            },
            getImportes(){
                let me=this;
                var url= '/dashboard';
                axios.get(url).then(function (response) {
                    var respuesta= response.data;
                    me.importes = respuesta.importes;
                    //cargamos los datos del chart
                    me.loadImportes();
                })
                .catch(function (error) {
                    console.log(error);
                });
            },
            loadImportes(){
                let me=this;
                me.importes.map(function(x){
                    me.varMesImporte.push(me.meses[(x.mes-1)]);
                    me.varTotalImporte.push(x.total);
                });
                me.varImporte=document.getElementById('importes').getContext('2d');

                me.charImporte = new Chart(me.varImporte, {
                    type: 'bar',
                    data: {
                        labels: me.varMesImporte,
                        datasets: [{
                            label: 'Ingreso',
                            data: me.varTotalImporte,
                            backgroundColor: '#39a53c',
                            borderColor: 'rgba(255, 99, 132, 0.2)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        scales: {
                            yAxes: [{
                                ticks: {
                                    beginAtZero:true
                                }
                            }]
                        }
                    }
                });
            }            
        },
        mounted() {
            this.getTransacciones();            
            this.getImportes(); 
        }
    }
</script>

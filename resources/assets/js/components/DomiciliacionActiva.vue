<template>
    <main class="main">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Escritorio</a></li>
        </ol>
        <div class="container-fluid">
            <div class="loader" v-if="loading"></div>
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-credit-card"></i>
                    Domiciliación Activa
                </div>
                <div class="card-body">
                    <div class="form-group row cdc-list-toolbar">
                        <div class="col-xl-7 col-lg-9 col-md-12 col-sm-12">
                            <div class="input-group">
                                <select class="form-control col-lg-3 col-md-4 col-sm-12" v-model="criterio">
                                    <option value="ClientReference">Ref. Cliente</option>
                                    <option value="Reference">Ref. Transacción</option>
                                    <option value="Description">Descripción</option>
                                    <option value="cliente_nombre">Cliente</option>
                                </select>
                                <input type="text" v-model="buscar" @keyup.enter="listarDomiciliaciones(1,buscar,criterio)" class="form-control" placeholder="Texto a buscar">
                                <button type="button" @click="listarDomiciliaciones(1,buscar,criterio)" class="btn btn-primary">
                                    <i class="fa fa-search"></i> Buscar
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="cdc-table-shell">
                        <table class="table table-bordered table-striped table-sm cdc-responsive-table">
                            <thead>
                                <tr>
                                    <th class="text-center cdc-sticky-col">Opciones
                                        <select v-model="offset" @change="listarDomiciliaciones(1,buscar,criterio)">
                                            <option value="10">10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                        </select>
                                    </th>
                                    <th class="text-center">Folio</th>
                                    <th class="text-center">Fecha</th>
                                    <th class="text-center">Cliente</th>
                                    <th class="text-center">Forma de Pago</th>
                                    <th class="text-center cdc-column-description">Descripción</th>
                                    <th class="text-center">Referencia</th>
                                    <th class="text-center">Monto</th>
                                    <th class="text-center">Próximo Cargo</th>
                                    <th class="text-center cdc-status-filter-heading">
                                        <span>Status</span>
                                        <select v-model="status" @change="listarDomiciliaciones(1,buscar,criterio)">
                                            <option value="99">Todos</option>
                                            <option value="1">Activo</option>
                                            <option value="2">Cancelado</option>
                                        </select>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="domiciliacion in arrayDomiciliacion" :key="domiciliacion.id">
                                    <td class="text-center cdc-sticky-col">
                                        <button v-if="domiciliacion.condicion == 1" type="button" @click="cancelarDomiciliacion(domiciliacion.id)" class="btn btn-danger btn-sm cdc-action-button" title="Cancelar domiciliación" aria-label="Cancelar domiciliación">
                                            <i class="fa fa-ban"></i>
                                        </button>
                                        <button v-if="domiciliacion.condicion == 1" type="button" @click="cargarDomiciliacion(domiciliacion.id)" class="btn btn-success btn-sm cdc-action-button" title="Realizar cargo recurrente manual" aria-label="Realizar cargo recurrente manual">
                                            <i class="fa fa-credit-card"></i>
                                        </button>
                                    </td>
                                    <td v-text="domiciliacion.folio" class="text-center"></td>
                                    <td class="text-center">
                                        <span class="cdc-date-stack">
                                            <span>{{ $formatDateMx(domiciliacion.fecha) }}</span>
                                            <span class="cdc-date-stack__time">{{ $formatTimeMx(domiciliacion.fecha) }}</span>
                                        </span>
                                    </td>
                                    <td v-text="domiciliacion.razon_social" class="text-center"></td>
                                    <td class="text-center">{{ formaPago(domiciliacion.PaymentTypes) }}</td>
                                    <td class="text-center cdc-column-description">{{ domiciliacion.Description }}</td>
                                    <td v-text="domiciliacion.ClientReference" class="text-center"></td>
                                    <td class="text-center">{{ $formatCurrency(domiciliacion.Amount / 100) }}</td>
                                    <td class="text-center">{{ $formatDateMx(domiciliacion.ProximoCargo) }}</td>
                                    <td class="text-center">
                                        <span v-if="domiciliacion.condicion == 1" class="badge badge-success">Activo</span>
                                        <span v-else-if="domiciliacion.condicion == 2" class="badge badge-danger">Cancelado</span>
                                        <span v-else class="badge badge-warning">Desconocido</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="cdc-table-footer">
                        <nav>
                            <ul class="pagination cdc-pagination">
                                <li class="page-item" :class="{disabled: pagination.current_page <= 1}">
                                    <a class="page-link" href="#" title="Primera página" aria-label="Primera página" @click.prevent="cambiarPagina(1,buscar,criterio)">
                                        <i class="fa fa-angle-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item" v-if="pagination.current_page > 1">
                                    <a class="page-link" href="#" @click.prevent="cambiarPagina(pagination.current_page - 1,buscar,criterio)">Ant</a>
                                </li>
                                <li class="page-item" v-for="page in pagesNumber" :key="page" :class="[page == isActived ? 'active' : '']">
                                    <a class="page-link" href="#" @click.prevent="cambiarPagina(page,buscar,criterio)" v-text="page"></a>
                                </li>
                                <li class="page-item" v-if="pagination.current_page < pagination.last_page">
                                    <a class="page-link" href="#" @click.prevent="cambiarPagina(pagination.current_page + 1,buscar,criterio)">Sig</a>
                                </li>
                                <li class="page-item" :class="{disabled: pagination.current_page >= pagination.last_page}">
                                    <a class="page-link" href="#" title="Última página" aria-label="Última página" @click.prevent="cambiarPagina(pagination.last_page,buscar,criterio)">
                                        <i class="fa fa-angle-double-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <div class="cdc-table-total">Total: {{ pagination.total || 0 }} registros</div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>

<script>
export default {
    data() {
        return {
            arrayDomiciliacion: [],
            pagination: {
                total: 0,
                current_page: 0,
                per_page: 0,
                last_page: 0,
                from: 0,
                to: 0,
            },
            offset: 10,
            status: 99,
            criterio: 'ClientReference',
            buscar: '',
            loading: false,
        };
    },
    computed: {
        isActived() {
            return this.pagination.current_page;
        },
        pagesNumber() {
            return this.$paginationPages(this.pagination);
        },
    },
    methods: {
        listarDomiciliaciones(page, buscar, criterio) {
            const me = this;
            const url = '/domiciliacion-activa?page=' + page
                + '&buscar=' + encodeURIComponent(buscar || '')
                + '&criterio=' + encodeURIComponent(criterio || 'ClientReference')
                + '&offset=' + me.offset
                + '&status=' + me.status;

            axios.get(url).then(function(response) {
                const respuesta = response.data;
                me.arrayDomiciliacion = respuesta.domiciliaciones.data;
                me.pagination = respuesta.pagination;
            }).catch(function(error) {
                swal('Error!', 'Error al listar las domiciliaciones. Error: ' + error, 'error');
                console.log(error);
            });
        },
        cambiarPagina(page, buscar, criterio) {
            if (!page || page < 1 || page > this.pagination.last_page) {
                return;
            }

            this.pagination.current_page = page;
            this.listarDomiciliaciones(page, buscar, criterio);
        },
        cancelarDomiciliacion(id) {
            const me = this;
            swal({
                title: '¿Está seguro de cancelar esta domiciliación?',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Aceptar',
                cancelButtonText: 'Cancelar',
                confirmButtonClass: 'btn btn-success',
                cancelButtonClass: 'btn btn-danger',
                buttonsStyling: false,
                reverseButtons: true,
            }).then((result) => {
                if (!result.value) {
                    return;
                }

                axios.put('/transaccion/rechazar', {id}).then(function(response) {
                    const respuesta = response.data;
                    if (respuesta.error === '') {
                        swal('Cancelación exitosa!', respuesta.msg, 'success');
                    } else {
                        swal('Error!', 'Error al realizar la cancelación. Error: ' + respuesta.error, 'error');
                    }
                    me.listarDomiciliaciones(me.pagination.current_page || 1, me.buscar, me.criterio);
                }).catch(function(error) {
                    swal('Error!', 'Error al cancelar la domiciliación. Error: ' + error, 'error');
                    console.log(error);
                });
            });
        },
        cargarDomiciliacion(id) {
            const me = this;
            swal({
                title: '¿Desea realizar el cargo recurrente manual?',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Aceptar',
                cancelButtonText: 'Cancelar',
                confirmButtonClass: 'btn btn-success',
                cancelButtonClass: 'btn btn-danger',
                buttonsStyling: false,
                reverseButtons: true,
            }).then((result) => {
                if (!result.value) {
                    return;
                }

                axios.post('/transaccionDom/registrar', {idtransaccion: id}).then(function(response) {
                    const respuesta = response.data;
                    if (respuesta.error === '') {
                        swal('Cargo registrado!', respuesta.msg, 'success');
                    } else {
                        swal('Error!', 'Error al realizar el cargo. Error: ' + respuesta.error, 'error');
                    }
                    me.listarDomiciliaciones(me.pagination.current_page || 1, me.buscar, me.criterio);
                }).catch(function(error) {
                    swal('Error!', 'Error al realizar el cargo recurrente. Error: ' + error, 'error');
                    console.log(error);
                });
            });
        },
        formaPago(paymentTypes) {
            if (paymentTypes == '401' || paymentTypes == '41') {
                return 'Visa y Mastercard';
            }

            if (paymentTypes == '1002' || paymentTypes == '102') {
                return 'American Express';
            }

            return 'NA';
        },
    },
    mounted() {
        this.listarDomiciliaciones(1, this.buscar, this.criterio);
    },
};
</script>

<template>
    <main class="main">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Escritorio</a></li>
        </ol>
        <div class="container-fluid">
            <div class="loader" v-if="loading"></div>
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-money"></i>
                    Pagos Recibidos
                </div>
                <div class="card-body">
                    <div class="form-group row cdc-list-toolbar">
                        <div class="col-xl-8 col-lg-10 col-md-12 col-sm-12">
                            <div class="input-group">
                                <select class="form-control col-lg-3 col-md-4 col-sm-12" v-model="criterio">
                                    <option value="folio">Folio</option>
                                    <option value="cliente">Cliente</option>
                                    <option value="referencia">Referencia</option>
                                    <option value="canal">Canal</option>
                                </select>
                                <input type="text" v-model="buscar" @keyup.enter="listarPagos(1,buscar,criterio)" class="form-control" placeholder="Texto a buscar">
                                <button type="button" @click="listarPagos(1,buscar,criterio)" class="btn btn-primary">
                                    <i class="fa fa-search"></i> Buscar
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="cdc-table-shell">
                        <table class="table table-bordered table-striped table-sm cdc-responsive-table">
                            <thead>
                                <tr>
                                    <th class="text-center">Opciones
                                        <select v-model="offset" @change="listarPagos(1,buscar,criterio)">
                                            <option value="10">10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                        </select>
                                    </th>
                                    <th class="text-center">Folio</th>
                                    <th class="text-center">Fecha</th>
                                    <th class="text-center">Cliente</th>
                                    <th class="text-center">Referencia</th>
                                    <th class="text-center">Monto</th>
                                    <th class="text-center">Canal</th>
                                    <th class="text-center cdc-status-filter-heading">
                                        <span>Status</span>
                                        <select v-model="status" @change="listarPagos(1,buscar,criterio)">
                                            <option value="99">Todos</option>
                                            <option value="activo">Activo</option>
                                            <option value="cancelado">Cancelado</option>
                                        </select>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="pago in arrayPagos" :key="pago.source_type + '-' + pago.source_id">
                                    <td class="text-center">
                                        <button type="button" class="btn btn-primary btn-sm cdc-action-button" title="Actualizar status" aria-label="Actualizar status" @click="actualizarStatus(pago)">
                                            <i class="fa fa-save"></i>
                                        </button>
                                    </td>
                                    <td v-text="pago.folio" class="text-center"></td>
                                    <td class="text-center">
                                        <span class="cdc-date-stack">
                                            <span>{{ $formatDateMx(pago.fecha) }}</span>
                                            <span class="cdc-date-stack__time">{{ $formatTimeMx(pago.fecha) }}</span>
                                        </span>
                                    </td>
                                    <td v-text="pago.cliente" class="text-center"></td>
                                    <td v-text="pago.referencia" class="text-center"></td>
                                    <td class="text-center">{{ $formatCurrency(pago.monto_centavos / 100) }}</td>
                                    <td class="text-center">{{ canalLabel(pago.canal) }}</td>
                                    <td class="text-center">
                                        <select class="form-control cdc-inline-status-select" v-model="pago.status">
                                            <option value="activo">Activo</option>
                                            <option value="cancelado">Cancelado</option>
                                        </select>
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
            arrayPagos: [],
            pagination: {
                total: 0,
                current_page: 0,
                per_page: 0,
                last_page: 0,
                from: 0,
                to: 0,
            },
            offset: 10,
            status: '99',
            criterio: 'cliente',
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
        listarPagos(page, buscar, criterio) {
            const me = this;
            const url = '/pagos-recibidos?page=' + page
                + '&buscar=' + encodeURIComponent(buscar || '')
                + '&criterio=' + encodeURIComponent(criterio || 'cliente')
                + '&offset=' + me.offset
                + '&status=' + encodeURIComponent(me.status);

            axios.get(url).then(function(response) {
                const respuesta = response.data;
                me.arrayPagos = respuesta.pagos.data;
                me.pagination = respuesta.pagination;
            }).catch(function(error) {
                swal('Error!', 'Error al listar pagos recibidos. Error: ' + error, 'error');
                console.log(error);
            });
        },
        actualizarStatus(pago) {
            axios.put('/pagos-recibidos/status', {
                source_type: pago.source_type,
                source_id: pago.source_id,
                status: pago.status,
            }).then(function() {
                swal('Actualización exitosa!', 'El status del pago fue actualizado.', 'success');
            }).catch(function(error) {
                swal('Error!', 'Error al actualizar el status. Error: ' + error, 'error');
                console.log(error);
            });
        },
        cambiarPagina(page, buscar, criterio) {
            if (!page || page < 1 || page > this.pagination.last_page) {
                return;
            }

            this.pagination.current_page = page;
            this.listarPagos(page, buscar, criterio);
        },
        canalLabel(canal) {
            if (canal === 'Domiciliacion') {
                return 'Domiciliación';
            }

            return canal;
        },
    },
    mounted() {
        this.listarPagos(1, this.buscar, this.criterio);
    },
};
</script>

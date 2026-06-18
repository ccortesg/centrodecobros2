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
                        <div class="col-lg-6 col-md-6 col-sm-12 col-12">
                            <div class="input-group">
                                <select class="form-control col-lg-3 col-md-4 col-sm-12" v-model="criterio">
                                    <option value="folio">Folio</option>
                                    <option value="cliente">Cliente</option>
                                    <option value="referencia">Referencia</option>
                                    <option value="canal">Canal</option>
                                </select>
                                <input type="text" v-model="buscar" @keyup.enter="listarPagos(1,buscar,criterio)" class="form-control" placeholder="Texto a buscar">
                            </div>
                        </div>
                    </div>
                    <div class="form-group row cdc-list-toolbar">
                        <div class="col-lg-6 col-md-6 col-sm-12 col-12">
                            <div class="input-group">
                                <span class="input-group-addon">Desde</span>
                                <input type="date" v-model="fechaInicio" class="form-control" @change="listarPagos(1,buscar,criterio)">
                                <span class="input-group-addon">Hasta</span>
                                <input type="date" v-model="fechaFin" class="form-control" @change="listarPagos(1,buscar,criterio)">
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
                                    <th class="text-center cdc-header-select-heading">
                                        <span>Folio</span>
                                        <select class="form-control form-control-sm cdc-header-page-size" v-model="offset" @change="listarPagos(1,buscar,criterio)">
                                            <option value="10">10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                        </select>
                                    </th>
                                    <th class="text-center">Fecha</th>
                                    <th class="text-center">Cliente</th>
                                    <th class="text-center">Referencia</th>
                                    <th class="text-center">Monto</th>
                                    <th class="text-center">Canal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="pago in arrayPagos" :key="pago.source_type + '-' + pago.source_id">
                                    <td v-text="pago.folio" class="text-center"></td>
                                    <td class="text-center">
                                        <span class="cdc-date-stack">
                                            <span>{{ $formatDateMx(pago.fecha) }}</span>
                                            <span class="cdc-date-stack__time">{{ $formatTimeMx(pago.fecha) }}</span>
                                        </span>
                                    </td>
                                    <td v-text="pago.cliente" class="text-center"></td>
                                    <td v-text="pago.referencia" class="text-center"></td>
                                    <td class="text-center">{{ $formatCurrency(pago.monto) }}</td>
                                    <td class="text-center">{{ canalLabel(pago.canal) }}</td>
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
            criterio: 'cliente',
            buscar: '',
            fechaInicio: '',
            fechaFin: '',
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
                + '&fechaInicio=' + encodeURIComponent(me.fechaInicio || '')
                + '&fechaFin=' + encodeURIComponent(me.fechaFin || '');

            axios.get(url).then(function(response) {
                const respuesta = response.data;
                me.arrayPagos = respuesta.pagos.data;
                me.pagination = respuesta.pagination;
            }).catch(function(error) {
                swal('Error!', 'Error al listar pagos recibidos. Error: ' + error, 'error');
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

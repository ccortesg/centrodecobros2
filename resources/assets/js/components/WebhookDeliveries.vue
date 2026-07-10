<template>
    <main class="main">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Escritorio</a></li>
            <li class="breadcrumb-item">Integraciones</li>
            <li class="breadcrumb-item active">Webhook Deliveries</li>
        </ol>

        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-exchange"></i> Webhook Deliveries
                    <button type="button" class="btn btn-success btn-sm" @click="exportar()">
                        <i class="fa fa-cloud-download"></i>&nbsp;Exportar
                    </button>
                </div>

                <div class="card-body">
                    <div class="form-group row">
                        <div class="col-lg-3 col-md-6 col-sm-12">
                            <label for="delivery-user">Cliente</label>
                            <select id="delivery-user" v-model.number="filters.user_id" class="form-control" @change="listar(1)">
                                <option :value="0">Todos</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">{{ etiquetaUsuario(user) }}</option>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6 col-sm-12">
                            <label for="delivery-event">Evento</label>
                            <select id="delivery-event" v-model="filters.event_type" class="form-control" @change="listar(1)">
                                <option value="">Todos</option>
                                <option v-for="event in events" :key="event.key" :value="event.key">{{ event.label }}</option>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6 col-sm-12">
                            <label for="delivery-status">Estado</label>
                            <select id="delivery-status" v-model="filters.status" class="form-control" @change="listar(1)">
                                <option value="">Todos</option>
                                <option value="pending">Pendiente</option>
                                <option value="processing">Procesando</option>
                                <option value="retrying">Reintentando</option>
                                <option value="delivered">Entregado</option>
                                <option value="dead">Agotado</option>
                                <option value="cancelled">Cancelado</option>
                                <option value="shadow">Shadow</option>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6 col-sm-12">
                            <label for="delivery-search">Texto a buscar</label>
                            <input id="delivery-search" v-model.trim="filters.buscar" type="text" class="form-control" @keyup.enter="listar(1)">
                        </div>
                    </div>

                    <div class="form-group row cdc-list-toolbar">
                        <div class="col-lg-8 col-md-10 col-sm-12 col-12">
                            <div class="input-group">
                                <span class="input-group-addon">Desde</span>
                                <input v-model="filters.fechaInicio" type="date" class="form-control">
                                <span class="input-group-addon">Hasta</span>
                                <input v-model="filters.fechaFin" type="date" class="form-control">
                                <button type="button" class="btn btn-primary" @click="listar(1)">
                                    <i class="fa fa-search"></i>&nbsp;Buscar
                                </button>
                                <button type="button" class="btn btn-secondary" @click="limpiarFiltros()">
                                    <i class="fa fa-eraser"></i>&nbsp;Limpiar
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="cdc-table-shell">
                        <table class="table table-bordered table-striped table-sm cdc-responsive-table">
                            <thead>
                                <tr>
                                    <th class="text-center cdc-sticky-col">
                                        Opciones
                                        <select v-model.number="filters.offset" class="cdc-header-page-size" @change="listar(1)">
                                            <option :value="10">10</option>
                                            <option :value="25">25</option>
                                            <option :value="50">50</option>
                                            <option :value="100">100</option>
                                        </select>
                                    </th>
                                    <th class="text-center">Fecha</th>
                                    <th>Evento</th>
                                    <th>Cliente</th>
                                    <th>Endpoint</th>
                                    <th>Host</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center">Intentos</th>
                                    <th class="text-center">HTTP</th>
                                    <th class="text-center">Origen</th>
                                    <th>Error</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="delivery in deliveries" :key="delivery.id">
                                    <td class="text-center cdc-sticky-col">
                                        <button type="button" class="btn btn-success btn-sm cdc-action-button" title="Ver detalle" aria-label="Ver detalle" @click="abrirDetalle(delivery)">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                        <button v-if="permiteReintento(delivery)" type="button" class="btn btn-warning btn-sm cdc-action-button" title="Reintentar" aria-label="Reintentar entrega" @click="reintentar(delivery)">
                                            <i class="fa fa-refresh"></i>
                                        </button>
                                        <button v-if="permiteCancelacion(delivery)" type="button" class="btn btn-danger btn-sm cdc-action-button" title="Cancelar" aria-label="Cancelar entrega" @click="cancelar(delivery)">
                                            <i class="fa fa-ban"></i>
                                        </button>
                                    </td>
                                    <td class="text-center">
                                        <span class="cdc-date-stack">
                                            <span>{{ $formatDateMx(delivery.created_at) }}</span>
                                            <span class="cdc-date-stack__time">{{ $formatTimeMx(delivery.created_at) }}</span>
                                        </span>
                                    </td>
                                    <td>{{ delivery.event_type }}</td>
                                    <td>{{ delivery.usuario || delivery.idusuario || '' }}</td>
                                    <td>{{ delivery.endpoint_name }}</td>
                                    <td>{{ delivery.host }}</td>
                                    <td class="text-center"><span :class="statusBadge(delivery.status)">{{ statusLabel(delivery.status) }}</span></td>
                                    <td class="text-center">{{ delivery.attempt_count }}</td>
                                    <td class="text-center">{{ delivery.last_status_code || '' }}</td>
                                    <td class="text-center">{{ delivery.source_context || '' }}</td>
                                    <td class="cdc-column-description">{{ delivery.last_error || '' }}</td>
                                </tr>
                                <tr v-if="deliveries.length === 0">
                                    <td colspan="11" class="text-center">No hay entregas para mostrar.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="cdc-table-footer">
                        <nav>
                            <ul class="pagination cdc-pagination">
                                <li class="page-item" :class="{disabled: pagination.current_page <= 1}">
                                    <a class="page-link" href="#" @click.prevent="cambiarPagina(1)"><i class="fa fa-angle-double-left"></i></a>
                                </li>
                                <li class="page-item" v-if="pagination.current_page > 1">
                                    <a class="page-link" href="#" @click.prevent="cambiarPagina(pagination.current_page - 1)">Ant</a>
                                </li>
                                <li v-for="page in pagesNumber" :key="page" class="page-item" :class="{active: page === pagination.current_page}">
                                    <a class="page-link" href="#" @click.prevent="cambiarPagina(page)">{{ page }}</a>
                                </li>
                                <li class="page-item" v-if="pagination.current_page < pagination.last_page">
                                    <a class="page-link" href="#" @click.prevent="cambiarPagina(pagination.current_page + 1)">Sig</a>
                                </li>
                                <li class="page-item" :class="{disabled: pagination.current_page >= pagination.last_page}">
                                    <a class="page-link" href="#" @click.prevent="cambiarPagina(pagination.last_page)"><i class="fa fa-angle-double-right"></i></a>
                                </li>
                            </ul>
                        </nav>
                        <div class="cdc-table-total">Total: {{ pagination.total || 0 }} registros</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade cdc-webhook-modal" tabindex="-1" :class="{'mostrar': detailModal}" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-primary modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Detalle de entrega</h4>
                        <button type="button" class="close" @click="cerrarDetalle()" aria-label="Cerrar"><span aria-hidden="true">x</span></button>
                    </div>
                    <div class="modal-body">
                        <pre class="cdc-audit-detail">{{ prettyDetail }}</pre>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="cerrarDetalle()">Cerrar</button>
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
            users: [],
            events: [],
            deliveries: [],
            detail: null,
            detailModal: false,
            filters: {
                user_id: 0,
                event_type: '',
                status: '',
                buscar: '',
                fechaInicio: '',
                fechaFin: '',
                offset: 50
            },
            pagination: {
                total: 0,
                current_page: 0,
                per_page: 50,
                last_page: 0,
                from: 0,
                to: 0
            }
        };
    },
    computed: {
        pagesNumber() {
            return this.$paginationPages(this.pagination);
        },
        prettyDetail() {
            return JSON.stringify(this.detail || {}, null, 2);
        }
    },
    mounted() {
        this.defaultDateRange();
        this.cargarCatalogos();
        this.listar(1);
    },
    methods: {
        defaultDateRange() {
            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth(), 1);
            this.filters.fechaInicio = this.inputDate(start);
            this.filters.fechaFin = this.inputDate(now);
        },
        inputDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        },
        etiquetaUsuario(user) {
            return user.nombre ? `${user.usuario} - ${user.nombre}` : user.usuario;
        },
        cargarCatalogos() {
            axios.get('/integraciones/webhooks/configuracion').then((response) => {
                this.users = response.data.users || [];
                this.events = response.data.events || [];
            }).catch((error) => this.mostrarError(error, 'No se pudieron cargar los catálogos.'));
        },
        query(page = 1) {
            const params = new URLSearchParams({
                page: String(page),
                user_id: String(this.filters.user_id || 0),
                event_type: this.filters.event_type || '',
                status: this.filters.status || '',
                buscar: this.filters.buscar || '',
                fechaInicio: this.filters.fechaInicio || '',
                fechaFin: this.filters.fechaFin || '',
                offset: String(this.filters.offset || 50)
            });
            return params.toString();
        },
        listar(page = 1) {
            axios.get(`/integraciones/webhooks/entregas?${this.query(page)}`).then((response) => {
                this.deliveries = (response.data.deliveries || {}).data || [];
                this.pagination = response.data.pagination || this.pagination;
            }).catch((error) => this.mostrarError(error, 'No se pudieron listar las entregas.'));
        },
        cambiarPagina(page) {
            if (!page || page < 1 || page > this.pagination.last_page) return;
            this.listar(page);
        },
        limpiarFiltros() {
            this.filters.user_id = 0;
            this.filters.event_type = '';
            this.filters.status = '';
            this.filters.buscar = '';
            this.defaultDateRange();
            this.listar(1);
        },
        abrirDetalle(delivery) {
            axios.get(`/integraciones/webhooks/entregas/${delivery.id}`).then((response) => {
                this.detail = response.data.delivery || null;
                this.detailModal = true;
            }).catch((error) => this.mostrarError(error, 'No se pudo cargar el detalle.'));
        },
        cerrarDetalle() {
            this.detailModal = false;
            this.detail = null;
        },
        permiteReintento(delivery) {
            return ['dead', 'cancelled'].includes(delivery.status);
        },
        permiteCancelacion(delivery) {
            return ['pending', 'retrying'].includes(delivery.status);
        },
        reintentar(delivery) {
            axios.post(`/integraciones/webhooks/entregas/${delivery.id}/reintentar`).then(() => {
                this.listar(this.pagination.current_page || 1);
                swal('Programado', 'La entrega fue enviada nuevamente a la cola.', 'success');
            }).catch((error) => this.mostrarError(error, 'No se pudo reintentar la entrega.'));
        },
        cancelar(delivery) {
            swal({
                title: 'Cancelar entrega',
                text: delivery.id,
                type: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Cancelar entrega',
                cancelButtonText: 'Regresar'
            }).then((result) => {
                if (!result.value) return;

                axios.post(`/integraciones/webhooks/entregas/${delivery.id}/cancelar`).then(() => {
                    this.listar(this.pagination.current_page || 1);
                }).catch((error) => this.mostrarError(error, 'No se pudo cancelar la entrega.'));
            });
        },
        exportar() {
            const url = `/integraciones/webhooks/entregas/exportar?${this.query(1)}`;
            axios({url, method: 'GET', responseType: 'blob'}).then((response) => {
                const objectUrl = window.URL.createObjectURL(new Blob([response.data]));
                const link = document.createElement('a');
                link.href = objectUrl;
                link.setAttribute('download', 'webhook_deliveries.xlsx');
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(objectUrl);
            }).catch((error) => this.mostrarError(error, 'No se pudo exportar la información.'));
        },
        statusLabel(status) {
            return {
                pending: 'Pendiente',
                processing: 'Procesando',
                retrying: 'Reintentando',
                delivered: 'Entregado',
                dead: 'Agotado',
                cancelled: 'Cancelado',
                shadow: 'Shadow'
            }[status] || status;
        },
        statusBadge(status) {
            if (status === 'delivered') return 'badge badge-success';
            if (['dead', 'cancelled'].includes(status)) return 'badge badge-danger';
            if (status === 'retrying') return 'badge badge-warning';
            if (status === 'shadow') return 'badge badge-info';
            return 'badge badge-secondary';
        },
        mostrarError(error, fallback) {
            const response = error && error.response ? error.response.data : null;
            const validation = response && response.errors
                ? Object.values(response.errors).flat().join(' ')
                : null;
            swal('Error', validation || (response && (response.msg || response.message)) || fallback, 'error');
        }
    }
};
</script>

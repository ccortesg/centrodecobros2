<template>
    <main class="main">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Escritorio</a></li>
        </ol>
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-plug"></i>
                    {{ title }}
                    <button type="button" @click="exportar()" class="btn btn-success btn-sm">
                        <i class="fa fa-cloud-download"></i>&nbsp;Exportar
                    </button>
                </div>

                <div class="card-body">
                    <div class="form-group row cdc-list-toolbar">
                        <div class="col-lg-6 col-md-6 col-sm-12 col-12">
                            <div class="input-group">
                                <input type="text" v-model="buscar" @keyup.enter="listar(1)" class="form-control" placeholder="Texto a buscar">
                            </div>
                        </div>
                    </div>

                    <div class="form-group row cdc-list-toolbar">
                        <div class="col-lg-6 col-md-6 col-sm-12 col-12">
                            <div class="input-group">
                                <span class="input-group-addon">Desde</span>
                                <input type="date" v-model="fechaInicio" class="form-control" @change="listar(1)">
                                <span class="input-group-addon">Hasta</span>
                                <input type="date" v-model="fechaFin" class="form-control" @change="listar(1)">
                                <button type="button" @click="listar(1)" class="btn btn-primary"><i class="fa fa-search"></i> Buscar</button>
                                <button type="button" @click="limpiarFiltros()" class="btn btn-secondary"><i class="fa fa-eraser"></i> Limpiar</button>
                            </div>
                        </div>
                    </div>

                    <div class="cdc-table-shell">
                        <table class="table table-bordered table-striped table-sm cdc-responsive-table">
                            <thead>
                                <tr>
                                    <th class="text-center cdc-sticky-col">
                                        Opciones
                                        <select v-model="offset" @change="listar(1)">
                                            <option value="10">10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                        </select>
                                    </th>
                                    <th class="text-center">Fecha</th>
                                    <template v-if="tipo === 'outgoing'">
                                        <th class="text-center">Proveedor</th>
                                        <th class="text-center">Contexto</th>
                                        <th class="text-center">Metodo</th>
                                        <th class="text-center">URL</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Duracion</th>
                                        <th class="text-center">Usuario</th>
                                        <th class="text-center">Referencia</th>
                                    </template>
                                    <template v-else-if="tipo === 'incoming'">
                                        <th class="text-center">Metodo</th>
                                        <th class="text-center">Ruta</th>
                                        <th class="text-center">Accion</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Duracion</th>
                                        <th class="text-center">IP</th>
                                        <th class="text-center">Referencia</th>
                                    </template>
                                    <template v-else>
                                        <th class="text-center">Usuario</th>
                                        <th class="text-center">Rol</th>
                                        <th class="text-center">Actividad</th>
                                        <th class="text-center">Modulo</th>
                                        <th class="text-center">IP</th>
                                        <th class="text-center">Resultado</th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="registro in arrayRegistros" :key="registro.id">
                                    <td class="text-center cdc-sticky-col">
                                        <button type="button" @click="abrirDetalle(registro)" class="btn btn-success btn-sm cdc-action-button" title="Ver detalle" aria-label="Ver detalle">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                    </td>
                                    <td class="text-center">
                                        <span class="cdc-date-stack">
                                            <span>{{ $formatDateMx(registro.occurred_at) }}</span>
                                            <span class="cdc-date-stack__time">{{ $formatTimeMx(registro.occurred_at) }}</span>
                                        </span>
                                    </td>
                                    <template v-if="tipo === 'outgoing'">
                                        <td v-text="registro.provider" class="text-center"></td>
                                        <td v-text="registro.source_context" class="text-center"></td>
                                        <td v-text="registro.method" class="text-center"></td>
                                        <td class="text-center cdc-column-description" v-text="registro.url"></td>
                                        <td class="text-center"><span :class="statusBadge(registro)">{{ registro.status_code || 'N/A' }}</span></td>
                                        <td class="text-center">{{ registro.duration_ms || 0 }} ms</td>
                                        <td v-text="registro.usuario_nombre || registro.idusuario || ''" class="text-center"></td>
                                        <td v-text="registro.correlation_reference" class="text-center"></td>
                                    </template>
                                    <template v-else-if="tipo === 'incoming'">
                                        <td v-text="registro.method" class="text-center"></td>
                                        <td v-text="registro.path" class="text-center"></td>
                                        <td class="text-center cdc-column-description" v-text="registro.route_action"></td>
                                        <td class="text-center"><span :class="statusBadge(registro)">{{ registro.status_code || 'N/A' }}</span></td>
                                        <td class="text-center">{{ registro.duration_ms || 0 }} ms</td>
                                        <td v-text="registro.ip_address" class="text-center"></td>
                                        <td v-text="registro.correlation_reference" class="text-center"></td>
                                    </template>
                                    <template v-else>
                                        <td v-text="registro.usuario" class="text-center"></td>
                                        <td v-text="registro.idrol" class="text-center"></td>
                                        <td v-text="registro.action" class="text-center"></td>
                                        <td v-text="registro.module_name" class="text-center"></td>
                                        <td v-text="registro.ip_address" class="text-center"></td>
                                        <td class="text-center"><span :class="statusBadge(registro)">{{ registro.success ? 'OK' : 'Error' }}</span></td>
                                    </template>
                                </tr>
                                <tr v-if="arrayRegistros.length === 0">
                                    <td :colspan="colspan" class="text-center">No hay registros para mostrar.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="cdc-table-footer">
                        <nav>
                            <ul class="pagination cdc-pagination">
                                <li class="page-item" :class="{disabled: pagination.current_page <= 1}">
                                    <a class="page-link" href="#" @click.prevent="cambiarPagina(1)">
                                        <i class="fa fa-angle-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item" v-if="pagination.current_page > 1">
                                    <a class="page-link" href="#" @click.prevent="cambiarPagina(pagination.current_page - 1)">Ant</a>
                                </li>
                                <li class="page-item" v-for="page in pagesNumber" :key="page" :class="[page == isActived ? 'active' : '']">
                                    <a class="page-link" href="#" @click.prevent="cambiarPagina(page)" v-text="page"></a>
                                </li>
                                <li class="page-item" v-if="pagination.current_page < pagination.last_page">
                                    <a class="page-link" href="#" @click.prevent="cambiarPagina(pagination.current_page + 1)">Sig</a>
                                </li>
                                <li class="page-item" :class="{disabled: pagination.current_page >= pagination.last_page}">
                                    <a class="page-link" href="#" @click.prevent="cambiarPagina(pagination.last_page)">
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

        <div class="modal fade" tabindex="-1" :class="{'mostrar': modal}" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-primary modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Detalle</h4>
                        <button type="button" class="close" @click="cerrarModal()" aria-label="Close">
                            <span aria-hidden="true">x</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <pre class="cdc-audit-detail">{{ prettyDetalle }}</pre>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="cerrarModal()">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>

<script>
    export default {
        props: ['tipo'],
        data() {
            return {
                arrayRegistros: [],
                buscar: '',
                fechaInicio: '',
                fechaFin: '',
                offset: 50,
                modal: false,
                detalle: null,
                pagination: {
                    total: 0,
                    current_page: 0,
                    per_page: 0,
                    last_page: 0,
                    from: 0,
                    to: 0
                }
            };
        },
        computed: {
            title() {
                if (this.tipo === 'outgoing') {
                    return 'Outgoing API Requests';
                }

                if (this.tipo === 'incoming') {
                    return 'Incoming API Requests';
                }

                return 'User Activity Log';
            },
            endpoint() {
                if (this.tipo === 'outgoing') {
                    return '/integraciones/outgoing-api-requests';
                }

                if (this.tipo === 'incoming') {
                    return '/integraciones/incoming-api-requests';
                }

                return '/integraciones/user-activity-log';
            },
            colspan() {
                if (this.tipo === 'outgoing') {
                    return 10;
                }

                if (this.tipo === 'incoming') {
                    return 9;
                }

                return 8;
            },
            isActived() {
                return this.pagination.current_page;
            },
            pagesNumber() {
                return this.$paginationPages(this.pagination);
            },
            prettyDetalle() {
                return JSON.stringify(this.detalle || {}, null, 2);
            }
        },
        methods: {
            defaultDateRange() {
                const now = new Date();
                const start = new Date(now.getFullYear(), now.getMonth(), 1);

                this.fechaInicio = this.formatInputDate(start);
                this.fechaFin = this.formatInputDate(now);
            },
            formatInputDate(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');

                return `${year}-${month}-${day}`;
            },
            listar(page = 1) {
                const url = `${this.endpoint}?page=${page}&buscar=${encodeURIComponent(this.buscar || '')}&fechaInicio=${this.fechaInicio || ''}&fechaFin=${this.fechaFin || ''}&offset=${this.offset}`;

                axios.get(url).then((response) => {
                    const registros = response.data.registros || {};
                    this.arrayRegistros = registros.data || [];
                    this.pagination = response.data.pagination || this.pagination;
                }).catch((error) => {
                    swal('Error!', 'Error al listar los registros.', 'error');
                    console.log(error);
                });
            },
            cambiarPagina(page) {
                if (!page || page < 1 || page > this.pagination.last_page) {
                    return;
                }

                this.listar(page);
            },
            limpiarFiltros() {
                this.buscar = '';
                this.defaultDateRange();
                this.listar(1);
            },
            exportar() {
                const url = `${this.endpoint}/exportar?buscar=${encodeURIComponent(this.buscar || '')}&fechaInicio=${this.fechaInicio || ''}&fechaFin=${this.fechaFin || ''}`;

                axios({
                    url,
                    method: 'GET',
                    responseType: 'blob'
                }).then((response) => {
                    const fileURL = window.URL.createObjectURL(new Blob([response.data]));
                    const fileLink = document.createElement('a');

                    fileLink.href = fileURL;
                    fileLink.setAttribute('download', `${this.tipo}.xlsx`);
                    document.body.appendChild(fileLink);
                    fileLink.click();
                    fileLink.remove();
                }).catch((error) => {
                    swal('Error!', 'Error al descargar el archivo.', 'error');
                    console.log(error);
                });
            },
            abrirDetalle(registro) {
                this.detalle = registro;
                this.modal = true;
            },
            cerrarModal() {
                this.modal = false;
                this.detalle = null;
            },
            statusBadge(registro) {
                return registro.success ? 'badge badge-success' : 'badge badge-danger';
            }
        },
        mounted() {
            this.defaultDateRange();
            this.listar(1);
        }
    };
</script>

<style>
    .cdc-audit-detail {
        background: #f8f9fa;
        border: 1px solid #dde2e6;
        border-radius: 4px;
        max-height: 60vh;
        overflow: auto;
        padding: 1rem;
        white-space: pre-wrap;
        word-break: break-word;
    }
</style>

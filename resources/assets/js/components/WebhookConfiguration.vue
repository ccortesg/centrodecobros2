<template>
    <main class="main">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Escritorio</a></li>
            <li class="breadcrumb-item">Integraciones</li>
            <li class="breadcrumb-item active">Webhook Configuration</li>
        </ol>

        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-random"></i> Webhook Configuration
                    <button type="button" class="btn btn-primary btn-sm" @click="abrirEndpoint()" :disabled="!selectedUserId">
                        <i class="fa fa-plus"></i>&nbsp;Endpoint
                    </button>
                </div>

                <div class="card-body">
                    <div v-if="!systemEnabled" class="alert alert-warning" role="alert">
                        El envío global de webhooks está deshabilitado. La configuración puede prepararse, pero no se publicarán eventos.
                    </div>

                    <div class="form-group row">
                        <div class="col-lg-6 col-md-8 col-sm-12">
                            <label for="webhook-user">Cliente</label>
                            <select id="webhook-user" v-model.number="selectedUserId" class="form-control" @change="cargarConfiguracion()">
                                <option :value="0" disabled>Seleccione un cliente</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">
                                    {{ etiquetaUsuario(user) }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <hr>
                    <h5>Modo de entrega y autenticación</h5>

                    <div class="form-group row align-items-end">
                        <div class="col-lg-3 col-md-6 col-sm-12">
                            <label for="webhook-mode">Modo</label>
                            <select id="webhook-mode" v-model="setting.mode" class="form-control">
                                <option value="legacy">Legacy</option>
                                <option value="shadow">Shadow</option>
                                <option value="hybrid">Hybrid</option>
                                <option value="active">Active</option>
                                <option value="disabled">Disabled</option>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6 col-sm-12 cdc-webhook-check-field">
                            <label class="form-check-label">
                                <input v-model="setting.hmac_enabled" type="checkbox" class="form-check-input">
                                HMAC-SHA256
                            </label>
                        </div>
                        <div v-if="setting.hmac_enabled" class="col-lg-4 col-md-8 col-sm-12">
                            <label for="webhook-secret">Nuevo secreto compartido</label>
                            <input id="webhook-secret" v-model="setting.hmac_secret" type="password" class="form-control" minlength="32" autocomplete="new-password" placeholder="Dejar vacío para conservar o generar">
                        </div>
                        <div v-if="setting.hmac_enabled && setting.hmac_configured" class="col-lg-2 col-md-4 col-sm-12 cdc-webhook-check-field">
                            <label class="form-check-label">
                                <input v-model="setting.rotate_secret" type="checkbox" class="form-check-input">
                                Rotar secreto
                            </label>
                        </div>
                    </div>

                    <div v-if="setting.hmac_configured" class="form-group">
                        <span class="badge badge-info">Fingerprint: {{ setting.hmac_secret_fingerprint }}</span>
                        <span v-if="setting.hmac_rotated_at" class="text-muted ml-2">Rotado: {{ $formatDateTimeMx(setting.hmac_rotated_at) }}</span>
                    </div>

                    <div class="form-group">
                        <button type="button" class="btn btn-primary" @click="guardarConfiguracion()" :disabled="saving || !selectedUserId">
                            <i class="fa fa-save"></i>&nbsp;Guardar configuración
                        </button>
                    </div>

                    <hr>
                    <h5>Endpoints</h5>

                    <div class="cdc-table-shell">
                        <table class="table table-bordered table-striped table-sm cdc-responsive-table">
                            <thead>
                                <tr>
                                    <th class="text-center">Opciones</th>
                                    <th>Nombre</th>
                                    <th>URL</th>
                                    <th class="text-center">Payload</th>
                                    <th class="text-center">ACK</th>
                                    <th class="text-center">Límite/min</th>
                                    <th class="text-center">Eventos</th>
                                    <th class="text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="endpoint in endpoints" :key="endpoint.id">
                                    <td class="text-center cdc-webhook-actions">
                                        <button type="button" class="btn btn-warning btn-sm cdc-action-button" title="Editar" aria-label="Editar endpoint" @click="abrirEndpoint(endpoint)">
                                            <i class="fa fa-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-info btn-sm cdc-action-button" title="Enviar prueba" aria-label="Enviar prueba" @click="probarEndpoint(endpoint)">
                                            <i class="fa fa-paper-plane"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm cdc-action-button" title="Eliminar" aria-label="Eliminar endpoint" @click="eliminarEndpoint(endpoint)">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                    <td>{{ endpoint.name }}</td>
                                    <td class="cdc-column-description">{{ endpoint.url }}</td>
                                    <td class="text-center">{{ payloadModeLabel(endpoint.payload_mode) }}</td>
                                    <td class="text-center">{{ ackModeLabel(endpoint.ack_mode) }}</td>
                                    <td class="text-center">{{ endpoint.rate_limit_per_minute }}</td>
                                    <td class="text-center">{{ suscripcionesActivas(endpoint).length }}</td>
                                    <td class="text-center">
                                        <span :class="endpoint.active ? 'badge badge-success' : 'badge badge-secondary'">
                                            {{ endpoint.active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="endpoints.length === 0">
                                    <td colspan="8" class="text-center">No hay endpoints configurados para este cliente.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade cdc-webhook-modal" tabindex="-1" :class="{'mostrar': endpointModal}" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-primary modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">{{ endpointForm.id ? 'Editar endpoint' : 'Nuevo endpoint' }}</h4>
                        <button type="button" class="close" @click="cerrarEndpoint()" aria-label="Cerrar">
                            <span aria-hidden="true">x</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label for="endpoint-name">Nombre</label>
                                <input id="endpoint-name" v-model.trim="endpointForm.name" type="text" class="form-control" maxlength="120">
                            </div>
                            <div class="col-md-6 cdc-webhook-check-field">
                                <label class="form-check-label">
                                    <input v-model="endpointForm.active" type="checkbox" class="form-check-input">
                                    Endpoint activo
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="endpoint-url">URL HTTPS</label>
                            <input id="endpoint-url" v-model.trim="endpointForm.url" type="url" class="form-control" maxlength="2048" placeholder="https://app.ejemplo.mx/webhooks/centro-de-cobros">
                        </div>

                        <div class="form-group row">
                            <div class="col-md-3">
                                <label for="endpoint-channel">Canal</label>
                                <select id="endpoint-channel" v-model="endpointForm.channel" class="form-control">
                                    <option value="generic">Genérico</option>
                                    <option value="donation">Donaciones</option>
                                    <option value="event">Eventos</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="payload-mode">Formato del payload</label>
                                <select id="payload-mode" v-model="endpointForm.payload_mode" class="form-control">
                                    <option value="legacy_exact">Legacy exact</option>
                                    <option value="soportetech_v1">Soportetech v1</option>
                                    <option value="soportetech_v1_1">Soportetech v1.1</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="ack-mode">Confirmación</label>
                                <select id="ack-mode" v-model="endpointForm.ack_mode" class="form-control">
                                    <option value="legacy_code_success">code = success</option>
                                    <option value="http_2xx">HTTP 2xx</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="rate-limit">Peticiones por minuto</label>
                                <input id="rate-limit" v-model.number="endpointForm.rate_limit_per_minute" type="number" class="form-control" min="1" max="30">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Eventos</label>
                            <div class="cdc-table-shell cdc-webhook-event-list">
                                <table class="table table-bordered table-sm">
                                    <thead>
                                        <tr>
                                            <th class="text-center">Enviar</th>
                                            <th>Evento</th>
                                            <th>Grupo</th>
                                            <th>Origen</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="event in subscribableEvents" :key="event.key">
                                            <td class="text-center">
                                                <input v-model="selectedEvents[event.key].enabled" type="checkbox">
                                            </td>
                                            <td>
                                                <strong>{{ event.label }}</strong>
                                                <div class="text-muted">{{ event.key }}</div>
                                            </td>
                                            <td>{{ event.group }}</td>
                                            <td>
                                                <select v-model="selectedEvents[event.key].source_filter" class="form-control form-control-sm" :disabled="!selectedEvents[event.key].enabled || !permiteFiltroOrigen(event.key)">
                                                    <option value="all">Todos</option>
                                                    <option value="manual">Manual</option>
                                                    <option value="api">API</option>
                                                    <option value="automatic">Automático</option>
                                                </select>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="cerrarEndpoint()">Cerrar</button>
                        <button type="button" class="btn btn-primary" @click="guardarEndpoint()" :disabled="savingEndpoint">
                            <i class="fa fa-save"></i>&nbsp;Guardar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade cdc-webhook-modal" tabindex="-1" :class="{'mostrar': secretModal}" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-primary" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Secreto HMAC generado</h4>
                    </div>
                    <div class="modal-body">
                        <label for="generated-secret">Secreto compartido</label>
                        <div class="input-group">
                            <input id="generated-secret" ref="generatedSecret" :value="generatedSecret" type="text" class="form-control" readonly>
                            <button type="button" class="btn btn-secondary" title="Copiar" aria-label="Copiar secreto" @click="copiarSecreto()">
                                <i class="fa fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" @click="cerrarSecreto()">Confirmar resguardo</button>
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
            systemEnabled: false,
            users: [],
            selectedUserId: 0,
            setting: {
                mode: 'legacy',
                hmac_enabled: false,
                hmac_configured: false,
                hmac_secret: '',
                hmac_secret_fingerprint: null,
                hmac_rotated_at: null,
                rotate_secret: false
            },
            endpoints: [],
            events: [],
            selectedEvents: {},
            endpointModal: false,
            endpointForm: {
                id: null,
                name: '',
                url: '',
                active: true,
                channel: 'donation',
                payload_mode: 'soportetech_v1_1',
                ack_mode: 'http_2xx',
                rate_limit_per_minute: 25
            },
            saving: false,
            savingEndpoint: false,
            secretModal: false,
            generatedSecret: ''
        };
    },
    computed: {
        subscribableEvents() {
            return this.events.filter((event) => event.key !== 'webhook.endpoint.test');
        }
    },
    mounted() {
        this.cargarConfiguracion();
    },
    methods: {
        emptySetting() {
            return {
                mode: 'legacy',
                hmac_enabled: false,
                hmac_configured: false,
                hmac_secret: '',
                hmac_secret_fingerprint: null,
                hmac_rotated_at: null,
                rotate_secret: false
            };
        },
        emptyEndpoint() {
            return {
                id: null,
                name: '',
                url: '',
                active: true,
                channel: 'donation',
                payload_mode: 'soportetech_v1_1',
                ack_mode: 'http_2xx',
                rate_limit_per_minute: 25
            };
        },
        etiquetaUsuario(user) {
            return user.nombre ? `${user.usuario} - ${user.nombre}` : user.usuario;
        },
        cargarConfiguracion() {
            const query = this.selectedUserId ? `?user_id=${this.selectedUserId}` : '';

            axios.get(`/integraciones/webhooks/configuracion${query}`).then((response) => {
                const data = response.data || {};
                this.systemEnabled = Boolean(data.system_enabled);
                this.users = data.users || [];
                this.selectedUserId = Number(data.selected_user_id || this.selectedUserId || 0);
                this.setting = Object.assign(this.emptySetting(), data.setting || {});
                this.endpoints = data.endpoints || [];
                this.events = data.events || [];
                this.inicializarEventos([]);
            }).catch((error) => this.mostrarError(error, 'No se pudo cargar la configuración.'));
        },
        guardarConfiguracion() {
            this.saving = true;

            axios.post('/integraciones/webhooks/configuracion', {
                user_id: this.selectedUserId,
                mode: this.setting.mode,
                hmac_enabled: Boolean(this.setting.hmac_enabled),
                hmac_secret: this.setting.hmac_secret || null,
                rotate_secret: Boolean(this.setting.rotate_secret)
            }).then((response) => {
                const generatedSecret = response.data.generated_secret || '';
                this.setting.hmac_secret = '';
                this.setting.rotate_secret = false;
                this.setting.hmac_configured = Boolean(this.setting.hmac_enabled);
                this.setting.hmac_secret_fingerprint = response.data.fingerprint || null;

                if (generatedSecret) {
                    this.generatedSecret = generatedSecret;
                    this.secretModal = true;
                } else {
                    swal('Guardado', 'La configuración fue actualizada.', 'success');
                }

                this.cargarConfiguracion();
            }).catch((error) => this.mostrarError(error, 'No se pudo guardar la configuración.'))
                .finally(() => { this.saving = false; });
        },
        abrirEndpoint(endpoint = null) {
            this.endpointForm = endpoint
                ? Object.assign(this.emptyEndpoint(), {
                    id: endpoint.id,
                    name: endpoint.name,
                    url: endpoint.url,
                    active: Boolean(endpoint.active),
                    channel: endpoint.channel || 'generic',
                    payload_mode: endpoint.payload_mode,
                    ack_mode: endpoint.ack_mode,
                    rate_limit_per_minute: Number(endpoint.rate_limit_per_minute)
                })
                : this.emptyEndpoint();
            this.inicializarEventos(endpoint ? this.suscripcionesActivas(endpoint) : []);
            this.endpointModal = true;
        },
        cerrarEndpoint() {
            this.endpointModal = false;
            this.endpointForm = this.emptyEndpoint();
        },
        inicializarEventos(subscriptions) {
            const selected = {};

            this.events.forEach((event) => {
                const subscription = subscriptions.find((item) => item.event_type === event.key && item.active !== false);
                selected[event.key] = {
                    enabled: Boolean(subscription),
                    source_filter: subscription ? subscription.source_filter : 'all'
                };
            });

            this.selectedEvents = selected;
        },
        suscripcionesActivas(endpoint) {
            return (endpoint.subscriptions || []).filter((subscription) => subscription.active !== false);
        },
        permiteFiltroOrigen(eventKey) {
            return eventKey.indexOf('recurring_charge.') === 0;
        },
        guardarEndpoint() {
            const subscriptions = Object.keys(this.selectedEvents)
                .filter((key) => this.selectedEvents[key].enabled && key !== 'webhook.endpoint.test')
                .map((key) => ({
                    event_type: key,
                    source_filter: this.permiteFiltroOrigen(key) ? this.selectedEvents[key].source_filter : 'all'
                }));

            if (!this.endpointForm.name || !this.endpointForm.url || subscriptions.length === 0) {
                swal('Atención', 'Capture nombre, URL y al menos un evento.', 'warning');
                return;
            }

            const payload = Object.assign({}, this.endpointForm, {
                user_id: this.selectedUserId,
                active: Boolean(this.endpointForm.active),
                rate_limit_per_minute: Number(this.endpointForm.rate_limit_per_minute),
                subscriptions
            });
            const request = this.endpointForm.id
                ? axios.put(`/integraciones/webhooks/endpoints/${this.endpointForm.id}`, payload)
                : axios.post('/integraciones/webhooks/endpoints', payload);

            this.savingEndpoint = true;
            request.then(() => {
                this.cerrarEndpoint();
                this.cargarConfiguracion();
                swal('Guardado', 'El endpoint fue actualizado.', 'success');
            }).catch((error) => this.mostrarError(error, 'No se pudo guardar el endpoint.'))
                .finally(() => { this.savingEndpoint = false; });
        },
        probarEndpoint(endpoint) {
            axios.post(`/integraciones/webhooks/endpoints/${endpoint.id}/test`).then(() => {
                swal('Programado', 'La entrega de prueba fue enviada a la cola.', 'success');
            }).catch((error) => this.mostrarError(error, 'No se pudo programar la prueba.'));
        },
        eliminarEndpoint(endpoint) {
            swal({
                title: 'Desactivar endpoint',
                text: endpoint.name,
                type: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (!result.value) return;

                axios.delete(`/integraciones/webhooks/endpoints/${endpoint.id}`).then(() => {
                    this.cargarConfiguracion();
                    swal('Eliminado', 'El endpoint fue desactivado.', 'success');
                }).catch((error) => this.mostrarError(error, 'No se pudo eliminar el endpoint.'));
            });
        },
        payloadModeLabel(mode) {
            if (mode === 'soportetech_v1_1') return 'Soportetech v1.1';
            return mode === 'soportetech_v1' ? 'Soportetech v1' : 'Legacy exact';
        },
        ackModeLabel(mode) {
            return mode === 'http_2xx' ? 'HTTP 2xx' : 'code=success';
        },
        copiarSecreto() {
            const input = this.$refs.generatedSecret;
            if (!input) return;

            input.select();
            input.setSelectionRange(0, input.value.length);
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(input.value);
            } else {
                document.execCommand('copy');
            }
        },
        cerrarSecreto() {
            this.secretModal = false;
            this.generatedSecret = '';
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

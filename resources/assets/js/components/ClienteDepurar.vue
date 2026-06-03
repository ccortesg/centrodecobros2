<template>
    <main class="main">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Escritorio</a></li>
            <li class="breadcrumb-item active">Catálogos / Depurar</li>
        </ol>

        <div class="container-fluid">
            <div class="loader" v-if="loading"></div>

            <div class="card">
                <div class="card-header">
                    <i class="fa fa-trash"></i> Depurar clientes
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Usuario</label>
                                <select class="form-control" v-model="idusuario" @change="listarClientes(1)">
                                    <option value="0">Seleccione...</option>
                                    <option v-for="usuario in arrayUsuarios" :key="usuario.id" :value="usuario.id">
                                        {{ usuario.usuario }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Búsqueda (nombre / email / celular)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" v-model="buscar" @keyup.enter="listarClientes(1)" placeholder="Texto a buscar">
                                    <button type="button" class="btn btn-primary" @click="listarClientes(1)"><i class="fa fa-search"></i> Buscar</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-danger btn-block" @click="confirmarDepuracion" :disabled="selectedIds.length < 1">
                                <i class="fa fa-trash"></i> Depurar
                            </button>
                        </div>
                    </div>

                    <div class="alert alert-warning" v-if="selectedIds.length > 0">
                        Seleccionados para depurar: <strong>{{ selectedIds.length }}</strong>
                    </div>

                    <table class="table table-bordered table-striped table-sm table-responsive">
                        <thead>
                            <tr>
                                <th style="width: 50px;">Sel.
                                    <select v-model="offset" @change="listarClientes(1)">
                                        <option value="10" selected>10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                </th>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Celular</th>
                                <th>Usuario</th>
                                <th>Creación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="cliente in arrayClientes" :key="cliente.id">
                                <td><input type="checkbox" :value="cliente.id" v-model="selectedIds"></td>
                                <td>{{ cliente.id }}</td>
                                <td>{{ cliente.nombre }}</td>
                                <td>{{ cliente.email || '-' }}</td>
                                <td>{{ cliente.telefono || '-' }}</td>
                                <td>{{ cliente.usuario || '-' }}</td>
                                <td>{{ formatDate(cliente.created_at) }}</td>
                            </tr>
                            <tr v-if="arrayClientes.length === 0">
                                <td colspan="7" class="text-center">No hay clientes elegibles para depuración con el criterio seleccionado.</td>
                            </tr>
                        </tbody>
                    </table>

                    <nav>
                        <ul class="pagination">
                            <li class="page-item" v-if="pagination.current_page > 1">
                                <a class="page-link" href="#" @click.prevent="cambiarPagina(pagination.current_page - 1)">Ant</a>
                            </li>
                            <li class="page-item" v-for="page in pagesNumber" :key="page" :class="[page === isActived ? 'active' : '']">
                                <a class="page-link" href="#" @click.prevent="cambiarPagina(page)" v-text="page"></a>
                            </li>
                            <li class="page-item" v-if="pagination.current_page < pagination.last_page">
                                <a class="page-link" href="#" @click.prevent="cambiarPagina(pagination.current_page + 1)">Sig</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </main>
</template>

<script>
export default {
    data () {
        return {
            loading: false,
            idusuario: 0,
            buscar: '',
            offset: 10,
            arrayUsuarios: [],
            arrayClientes: [],
            selectedIds: [],
            pagination: {
                total: 0,
                current_page: 1,
                per_page: 10,
                last_page: 1,
                from: 0,
                to: 0
            },
            offsetPage: 3
        }
    },
    computed: {
        isActived () {
            return this.pagination.current_page;
        },
        pagesNumber () {
            if (!this.pagination.to) {
                return [];
            }

            var from = this.pagination.current_page - this.offsetPage;
            if (from < 1) {
                from = 1;
            }

            var to = from + (this.offsetPage * 2);
            if (to >= this.pagination.last_page) {
                to = this.pagination.last_page;
            }

            var pagesArray = [];
            while (from <= to) {
                pagesArray.push(from);
                from++;
            }

            return pagesArray;
        }
    },
    methods: {
        listarUsuarios () {
            var me = this;
            axios.get('/user/selectUsuario').then(function (response) {
                me.arrayUsuarios = response.data.usuarios || [];
            }).catch(function (error) {
                console.log(error);
            });
        },
        listarClientes (page) {
            var me = this;
            if (parseInt(this.idusuario) <= 0) {
                this.arrayClientes = [];
                this.pagination = { total: 0, current_page: 1, per_page: 10, last_page: 1, from: 0, to: 0 };
                this.selectedIds = [];
                return;
            }

            this.loading = true;
            axios.get('/cliente/depurar', {
                params: {
                    page: page,
                    idusuario: this.idusuario,
                    buscar: this.buscar,
                    offset: this.offset
                }
            }).then(function (response) {
                me.arrayClientes = response.data.clientes.data;
                me.pagination = response.data.pagination;
                me.selectedIds = me.selectedIds.filter(function (id) {
                    return me.arrayClientes.some(function (cliente) { return cliente.id === id; });
                });
                me.loading = false;
            }).catch(function (error) {
                me.loading = false;
                swal('Error', me.getErrorMsg(error), 'error');
            });
        },
        cambiarPagina (page) {
            this.pagination.current_page = page;
            this.listarClientes(page);
        },
        confirmarDepuracion () {
            if (this.selectedIds.length < 1) {
                swal('Validación', 'Debes seleccionar al menos un cliente.', 'warning');
                return;
            }

            var me = this;
            swal({
                title: '¿Depurar clientes?',
                html: 'Se eliminarán físicamente <b>' + this.selectedIds.length + '</b> clientes/personas elegibles.',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, depurar',
                cancelButtonText: 'Cancelar'
            }).then(function (result) {
                if (result.value) {
                    me.depurar();
                }
            });
        },
        depurar () {
            var me = this;
            this.loading = true;
            axios.post('/cliente/depurar/eliminar', {
                idusuario: this.idusuario,
                cliente_ids: this.selectedIds
            }).then(function (response) {
                me.loading = false;
                me.selectedIds = [];
                swal('Correcto', response.data.msg, 'success');
                me.listarClientes(1);
            }).catch(function (error) {
                me.loading = false;
                swal('Error', me.getErrorMsg(error), 'error');
            });
        },
        formatDate (dateValue) {
            if (!dateValue) {
                return 'Sin fecha (se usa ID)';
            }
            return dateValue;
        },
        getErrorMsg (error) {
            if (error && error.response && error.response.data) {
                return error.response.data.msg || error.response.data.message || 'Ocurrió un error.';
            }
            return 'Ocurrió un error.';
        }
    },
    mounted () {
        this.listarUsuarios();
    }
}
</script>

<template>
    <main class="main">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Escritorio</a></li>
            <li class="breadcrumb-item active">Catálogos / Consolidar</li>
        </ol>

        <div class="container-fluid">
            <div class="loader" v-if="loading"></div>

            <div class="card">
                <div class="card-header">
                    <i class="fa fa-compress"></i> Consolidar clientes
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
                            <button type="button" class="btn btn-success btn-block" @click="prepararCombinacion" :disabled="selectedIds.length < 2">
                                <i class="fa fa-random"></i> Combinar
                            </button>
                        </div>
                    </div>

                    <div class="alert alert-info" v-if="selectedIds.length > 0">
                        Seleccionados: <strong>{{ selectedIds.length }}</strong>
                        <span v-if="keepPreview.id"> | Principal estimado: <strong>#{{ keepPreview.id }} - {{ keepPreview.nombre }}</strong></span>
                    </div>

                    <table class="table table-bordered table-striped table-sm table-responsive">
                        <thead>
                            <tr>
                                <th style="width: 50px;">Sel.
                                    <select v-model="offset" @change="listarClientes(1)">
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50" selected>50</option>
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
                            <tr v-for="cliente in arrayClientes" :key="cliente.id" :class="rowClass(cliente)">
                                <td><input type="checkbox" :value="cliente.id" v-model="selectedIds"></td>
                                <td>{{ cliente.id }}</td>
                                <td>{{ cliente.nombre }}</td>
                                <td>{{ cliente.email || '-' }}</td>
                                <td>{{ cliente.telefono || '-' }}</td>
                                <td>{{ cliente.usuario || '-' }}</td>
                                <td>{{ formatDate(cliente.created_at) }}</td>
                            </tr>
                            <tr v-if="arrayClientes.length === 0">
                                <td colspan="7" class="text-center">No hay resultados para el criterio seleccionado.</td>
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
            offset: 50,
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
        },
        keepPreview () {
            if (this.selectedIds.length < 2) {
                return {};
            }

            var selected = this.arrayClientes.filter(cliente => this.selectedIds.indexOf(cliente.id) !== -1);
            if (selected.length < 2) {
                return {};
            }

            selected.sort(function (a, b) {
                if (!a.created_at && !b.created_at) {
                    return a.id - b.id;
                }
                if (!a.created_at) {
                    return 1;
                }
                if (!b.created_at) {
                    return -1;
                }

                if (a.created_at === b.created_at) {
                    return a.id - b.id;
                }

                return new Date(a.created_at) - new Date(b.created_at);
            });

            return selected[0];
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
            axios.get('/cliente/consolidar', {
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
        prepararCombinacion () {
            if (this.selectedIds.length < 2) {
                swal('Validación', 'Debes seleccionar al menos dos clientes.', 'warning');
                return;
            }

            var me = this;
            var keep = this.keepPreview;
            swal({
                title: '¿Combinar clientes? ',
                html: 'Se conservará como principal: <b>#' + keep.id + ' - ' + keep.nombre + '</b><br>Se eliminarán ' + (this.selectedIds.length - 1) + ' clientes secundarios.',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, combinar',
                cancelButtonText: 'Cancelar'
            }).then(function (result) {
                if (result.value) {
                    me.combinar();
                }
            });
        },
        combinar () {
            var me = this;
            this.loading = true;
            axios.post('/cliente/consolidar/combinar', {
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
        rowClass (cliente) {
            if (!this.keepPreview.id) {
                return '';
            }
            if (cliente.id === this.keepPreview.id && this.selectedIds.indexOf(cliente.id) !== -1) {
                return 'table-success';
            }
            return '';
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

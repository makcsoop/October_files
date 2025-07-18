Vue.component('dashboard-component-dashboard-dashboard-selector', {
    props: {
        store: Object,
        embeddedInDashboard: Boolean
    },
    computed: {
        canCreateAndEdit: function () {
            return this.store.state.canCreateAndEdit;
        },

        currentDashboard: function () {
            return this.store.getCurrentDashboard();
        }
    },
    data: function () {
        return {
            editMenuItems: [],
        };
    },
    methods: {
        setEditMenuItems: function () {
            this.editMenuItems = [
                {
                    type: 'text',
                    command: 'edit',
                    label: oc.lang.get('dashboard.edit_dashboard')
                },
                // {
                //     type: 'text',
                //     command: 'rename',
                //     label: oc.lang.get('dashboard.rename_dashboard')
                // },
                // {
                //     type: 'text',
                //     command: 'delete',
                //     label: oc.lang.get('dashboard.delete_dashboard')
                // },
                // {
                //     type: 'separator'
                // },
                // {
                //     type: 'text',
                //     href: this.store.manageUrl,
                //     target: '_blank',
                //     label: oc.lang.get('dashboard.manage_dashboards')
                // }
                // {
                //     type: 'text',
                //     href: '/export/url/here' + this.store.state.dashboardCode,
                //     target: '_blank',
                //     label: oc.lang.get('dashboard.export_dashboard')
                // }
            ];

            if (this.store.manageUrl) {
                this.editMenuItems.push(
                    {
                        type: 'separator'
                    },
                    {
                        type: 'text',
                        href: this.store.manageUrl,
                        label: oc.lang.get('dashboard.manage_dashboards')
                    }
                );
            }
        },

        onEditClick: function (ev) {
            this.setEditMenuItems();
            this.$refs.editMenu.showMenu(ev);
        },

        onEditMenuItemCommand: function (command) {
            // Let the dropdown menu hide before
            // running the next operation.
            Vue.nextTick(() => {
                if (command === 'edit') {
                    this.store.startEditing();
                }
            })
        },

        onKeyDown: function onKeyDown(ev) {
            if (ev.keyCode == 27) {
                this.hideDropdown();
            }
        },
    },
    mounted: function onMounted() {
    },
    watch: {
    },
    beforeDestroy: function beforeDestroy() {
    },
    template: '#dashboard_vuecomponents_dashboard_dashboard_selector'
});

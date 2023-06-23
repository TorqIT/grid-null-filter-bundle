pimcore.registerNS("pimcore.plugin.TorqITGridNullFilterBundle");

pimcore.plugin.TorqITGridNullFilterBundle = Class.create(pimcore.plugin.admin, {
    getClassName: function () {
        return "pimcore.plugin.TorqITGridNullFilterBundle";
    },

    initialize: function () {
        pimcore.plugin.broker.registerPlugin(this);
    },

    pimcoreReady: function (params, broker) {
        // alert("TorqITGridNullFilterBundle ready!");
    }
});

const TorqITGridNullFilterBundlePlugin = new pimcore.plugin.TorqITGridNullFilterBundle();

(function InitializeGridNullFilter() {
    const IsNullName = 'isNullOrEmpty';

    const addNullFilterToGrid = function (e) {

        if (e.xtype !== 'gridpanel' || e.bodyCls !== 'pimcore_editable_grid' || !e.cls.includes("pimcore_object_grid_panel") ) {  // e.getStore().getProxy().type != 'ajax') {
            return;
        }

        const gridStore = e.getStore();
        gridStore.on('filterchange', (store, filters) => clearActiveClasses(e, store, filters));

        const menu = e.headerCt.getMenu();

        menu.on('beforeshow', setCheckboxState);

        menu.add({
            xtype: 'menucheckitem',
            name: IsNullName,
            text: 'Filter For Null/Empty',
            handler: function (m) {

                const column = menu.activeHeader;
                const dataIndex = column.dataIndex;

                if (m.checked) {
                    const nullFilter = new Ext.util.Filter({
                        dataIndex: dataIndex,
                        property: dataIndex,
                        // value: 'NULL', Do not pass value to bypass pimcore filter processing, with a value a condition will be added which we need to override
                        operator: '',
                        type: 'isNullOrEmpty',
                    });

                    gridStore.addFilter([nullFilter]);
                    column.filter.setColumnActive(true);

                } else {
                    const nullFilter = findFilter(gridStore.getFilters(), dataIndex, IsNullName);
                    if (nullFilter) gridStore.removeFilter(nullFilter);
                    column.filter.setColumnActive(false);
                }
            }
        });
    };

    const clearActiveClasses = function (grid, store, filters) {
            if (filters && filters.length === 0) {
                const columns = grid.getColumns();

                for (const column of columns) {
                    if (!column.filter)
                        continue;
                    column.filter.setColumnActive(false);
                }
        }
    }

    const setCheckboxState = function (e) {
        const column = e.up();
        const columnName = column.config.dataIndex;
        const store = column.up().up().store;
        const filters = store.getFilters();

        const matchedFilter = findFilter(filters, columnName, IsNullName)
        const isChecked = !!matchedFilter;

        const nullCheckMenuITem = e.down("[text=Filter For Null/Empty]");
        nullCheckMenuITem.setChecked(isChecked);
    }

    const findFilter = function(filters, columnName, type) {
        for (const item of filters.items) {
            if (item.type === type && item.getProperty() === columnName) {
                return item;
            }
        }

        return null;
    }

    Ext.on('added', addNullFilterToGrid);
})();

pimcore.registerNS("pimcore.plugin.TorqITGridNullFilterBundle");

pimcore.plugin.TorqITGridNullFilterBundle = Class.create({
  initialize: function () {
    Ext.on("added", this.addNullFilterToGrid.bind(this));
  },

  addNullFilterToGrid: function (e) {
    if (
      e.xtype !== "gridpanel" ||
      e.bodyCls !== "pimcore_editable_grid" ||
      !e.cls.includes("pimcore_object_grid_panel")
    ) {
      return;
    }

    const gridStore = e.getStore();
    gridStore.on("filterchange", (store, filters) =>
      this.clearActiveClasses(e, store, filters)
    );

    const menu = e.headerCt.getMenu();

    menu.on("beforeshow", this.setCheckboxState.bind(this));

    menu.add({
      xtype: "menucheckitem",
      name: "isNullOrEmpty",
      text: "Filter For Null/Empty",
      handler: function (m) {
        const column = menu.activeHeader;
        let dataIndex = column.dataIndex;
        const type = column?.filter?.type;

        if (type === "quantityValue") {
          dataIndex = dataIndex + "__value";
        }

        if (m.checked) {
          const nullFilter = new Ext.util.Filter({
            dataIndex: dataIndex,
            property: dataIndex,
            // value: 'NULL', Do not pass value to bypass pimcore filter processing, with a value a condition will be added which we need to override
            operator: "",
            type: "isNullOrEmpty",
          });

          gridStore.addFilter([nullFilter]);
          column.filter.setColumnActive(true);
        } else {
          const nullFilter = this.findFilter(
            gridStore.getFilters(),
            dataIndex,
            "isNullOrEmpty"
          );
          if (nullFilter) gridStore.removeFilter(nullFilter);
          column.filter.setColumnActive(false);
        }
      }.bind(this),
    });
  },

  clearActiveClasses: function (grid, store, filters) {
    if (filters && filters.length === 0) {
      const columns = grid.getColumns();

      for (const column of columns) {
        if (!column.filter) continue;
        column.filter.setColumnActive(false);
      }
    }
  },

  setCheckboxState: function (e) {
    const column = e.up();
    const columnName = column.config.dataIndex;
    const store = column.up().up().store;
    const filters = store.getFilters();

    const matchedFilter = this.findFilter(filters, columnName, "isNullOrEmpty");
    const isChecked = !!matchedFilter;

    const nullCheckMenuITem = e.down("[text=Filter For Null/Empty]");
    nullCheckMenuITem.setChecked(isChecked);
  },

  findFilter: function (filters, columnName, type) {
    for (const item of filters.items) {
      if (item.type === type && item.getProperty() === columnName) {
        return item;
      }
    }

    return null;
  },
});

const TorqITGridNullFilterBundlePlugin =
  new pimcore.plugin.TorqITGridNullFilterBundle();

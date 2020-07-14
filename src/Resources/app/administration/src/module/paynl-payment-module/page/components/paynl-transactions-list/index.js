import template from './transactions-list.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.extend('paynl-transactions-list', 'sw-data-grid', {
    template,

    props: {
        detailRoute: {
            type: String,
            required: false
        },
        repository: {
            required: true,
            type: Object
        },
        items: {
            type: Array,
            required: false,
            default: null
        },
        dataSource: {
            type: [Array, Object],
            required: false
        },
        showSettings: {
            type: Boolean,
            default: true,
            required: false
        },
        fullPage: {
            type: Boolean,
            default: true,
            required: false
        },
        allowInlineEdit: {
            type: Boolean,
            default: true,
            required: false
        },
        allowColumnEdit: {
            type: Boolean,
            default: true,
            required: false
        },
        sortIsAllowed: {
            type: Boolean,
            default: true,
            required: false
        }
    },

    data() {
        return {
            deleteId: null,
            showBulkDeleteModal: false,
            isBulkLoading: false,
            page: 1,
            limit: 25,
            total: 10
        };
    },

    watch: {
        items() {
            this.applyResult(this.items);
        }
    },

    methods: {
        createdComponent() {
            this.initGridColumns();

            if (this.items) {
                this.applyResult(this.items);
            }
        },

        applyResult(result) {
            this.records = result;
            this.total = result.total;
            this.page = result.criteria.page;
            this.limit = result.criteria.limit;
            this.loading = false;

            this.$emit('update-records', result);
        },

        doSearch() {
            this.loading = true;
            return this.repository.search(this.items.criteria, this.items.context).then(this.applyResult);
        },

        revert() {
            // reloads the grid to revert all changes
            const promise = this.doSearch();
            this.$emit('inline-edit-cancel', promise);

            return promise;
        },

        sort(column) {
            if (!column.sortIsAllowed) {
                return false;
            }

            this.items.criteria.resetSorting();

            let direction = 'ASC';
            if (this.currentSortBy === column.dataIndex) {
                if (this.currentSortDirection === direction) {
                    direction = 'DESC';
                }
            }

            column.dataIndex.split(',').forEach((field) => {
                this.items.criteria.addSorting(
                    Criteria.sort(field, direction, column.naturalSorting)
                );
            });

            this.currentSortBy = column.dataIndex;
            this.currentSortDirection = direction;
            this.currentNaturalSorting = column.naturalSorting;
            this.$emit('column-sort', column);

            return this.doSearch();
        },

        paginate({ page = 1, limit = 25 }) {
            this.items.criteria.setPage(page);
            this.items.criteria.setLimit(limit);

            return this.doSearch();
        }
    }
});

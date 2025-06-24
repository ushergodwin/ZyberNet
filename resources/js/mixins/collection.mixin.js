import { ref, VueElement } from "vue";
import InfiniteLoading from "v3-infinite-loading";
import "v3-infinite-loading/lib/style.css";
import { debounce } from "lodash";
import axios from "axios";

export default {
    data() {
        return {
            search: null,
            filter: "",
            asc_desc: "asc",
            items: [],
            page: 1,
            url: "",
            infiniteId: +new Date(),
            queryFilter: "",
        };
    },
    watch: {
        search: debounce(function () {
            this.queryFilter = "";
            this.filterChanged();
        }, 800),
    },

    mounted() {
        // this.filterChanged();
        // if (this.items.length == 0) {
        //     Vue.nextTick().then(() => {
        //         console.log(document.body.scrollHeight);
        //         window.scrollTo(0, 10);
        //         window.scrollTo(0, 0);
        //     });
        // }
    },
    methods: {
        addItems(items) {
            if (this.search) {
                this.emptyItems();
            } else if (this.search == "") {
                this.search = null;
                this.filterChanged();
            }

            this.items = [...items];
        },
        deleteItem(itemId) {
            let foundItem = this.items.find((item) => item.id === itemId);
            this.items.splice(this.items.indexOf(foundItem), 1);
        },
        emptyItems() {
            this.items = [];
            this.items.length = 0;
        },

        addItem(item) {
            this.items.unshift(item);
        },

        updateItem(data) {
            let foundItem = this.items.find((item) => item.id === data.id);

            for (let i in data) {
                foundItem[i] = data[i];
            }
        },

        changeFilter(filter) {
            this.search = "";
            if (this.asc_desc == "asc") {
                this.asc_desc = "desc";
            } else {
                this.asc_desc = "asc";
            }
            this.filter = filter;
            this.filterChanged();
        },

        async infiniteHandler($state) {
            try {
                let res = await axios.get(this.url, {
                    params: {
                        search: this.search,
                        page: this.page,
                        queryFilter: this.filter,
                        asc_desc: this.asc_desc,
                    },
                });
                this.storeData(res, $state);
            } catch (e) {
                console.log(e);
            }
        },

        storeData({ data }, $state) {
            data = data.results;

            if (data.data.length) {
                this.page += 1;
                this.addItems(data.data);
                $state.loaded();
            }

            if (data.data.length == 0) {
                $state.complete();
            }

            if (data.to === data.total) {
                $state.complete();
            }
        },

        filterChanged() {
            this.emptyItems();
            this.page = 1;
            this.infiniteId += 1;
        },
    },

    components: {
        InfiniteLoading,
    },
};

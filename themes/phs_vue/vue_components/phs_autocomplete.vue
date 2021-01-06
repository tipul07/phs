<template>
<v-select label="listing_title" :placeholder="placeholder"
          v-model="localval"
          :options="options" @search="onsearch">
    <template v-slot:no-options="{ search, searching, loading }">
        <div v-if="searching || loading" v-html="searchNoResultsText" style="opacity: 0.7;"></div>
        <div v-else v-html="defaultSearchText" style="opacity: 0.7;"></div>
    </template>
    <template v-slot:option="option">
        <slot name="phs-option">
        <div class="d-center" v-html="get_listing_item_label( option )"></div>
        </slot>
    </template>
    <template v-slot:selected-option="option">
        <slot name="phs-selected-option">
        <div class="selected d-center" v-html="get_selected_item_label()"></div>
        </slot>
    </template>
</v-select>
</template>

<script>
module.exports = {
    name: "phsAutocomplete",
    props: {
        phsVueApp: {
            type: Object,
            default: () => null
        },
        placeholder: {
            type: String,
            default: ""
        },
        searchNoResultsText: {
            type: String,
            default: ""
        },
        defaultSearchText: {
            type: String,
            default: ""
        },
        ajaxRoute: {
            type: String,
            default: ""
        },
        ajaxUrlGetParams: {
            type: Object,
            default: () => ({ q: "", limit: 20 })
        },
        ajaxParams: {
            type: Object,
            default: () => ({
                queue_request: true,
                queue_response_cache: true,
                queue_response_cache_timeout: 10,
                stack_request: true
            })
        },
        ajaxRequestError: {
            type: String,
            default: "Error retrieving items list for autocomplete. Please try again."
        },
        value: {
            type: [Object],
            default: () => null
        },
        inputLazyness: {
            type: Number,
            default: 200
        }
    },
    data: function() {
        return {
            options: []
        }
    },
    computed: {
        localval: {
            get() { return this.value; },
            set( val ) { this.$emit( 'input', val ); }
        }
    },
    created: function() {
        this.phs_autocomplete_search = _.debounce( ( loading, search, vm ) => {
            let app = null;
            if( typeof vm.phsVueApp !== "undefined" && vm.phsVueApp )
                app = vm.phsVueApp;
            else if( typeof vm.$root !== "undefined" && vm.$root )
                app = vm.$root;

            if( !app )
            {
                console.warn( "Couldn't determine Vue application. Use phsVueApp parameter to pass autocomplete a Vue application instance." );
                return;
            }

            let ajax_params = vm.ajaxUrlGetParams;
            if( typeof ajax_params.limit === "undefined" )
                ajax_params.limit = 20;
            ajax_params.q = search;

            app.read_data(
                vm.ajaxRoute,
                ajax_params,
                function( data, status, ajax_obj ) {
                    loading( false );

                    if( app.valid_default_response_from_read_data
                        && !app.valid_default_response_from_read_data( data ) ) {
                        let error_msg = vm.ajaxRequestError;
                        let extra_error = app.get_error_message_for_default_read_data( data );
                        if( extra_error )
                            error_msg += ": " + extra_error;

                        app.error_message( error_msg, 10 );
                        return;
                    }

                    if( typeof data.response.items === "undefined" )
                        data.response.items = [];

                    vm.options = data.response.items;
                },
                function() {
                    loading( false );
                    if( app.error_message )
                        app.error_message( vm.ajaxRequestError );
                }, vm.ajaxParams
            );
        }, this.inputLazyness );
    },
    methods: {
        get_listing_item_label: function( option_item ) {
            if( typeof option_item === "undefined"
             || !option_item )
                return "";

            if( typeof option_item.listing_title_html === "string"
             && option_item.listing_title_html.length > 0 )
                return option_item.listing_title_html;

            if( typeof option_item.listing_title === "string"
             && option_item.listing_title.length > 0 )
                return option_item.listing_title;

            return "";
        },
        get_selected_item_label: function( option_item = null ) {
            if( option_item === null )
                option_item = this.localval;

            if( typeof option_item === "undefined"
             || !option_item
             || typeof option_item.listing_title === "undefined" )
                return "";

            return option_item.listing_title;
        },
        onsearch( search, loading ) {
            if( !this.ajaxRoute || this.ajaxRoute.length === 0 )
                return;

            loading( true );
            this.phs_autocomplete_search( loading, search, this );
        },
        phs_autocomplete_search: function( loading, search, vm ) {
            console.log( "Not implemented yet!" );
        }
    }
};
</script>

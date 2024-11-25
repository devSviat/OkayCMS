$(document).ready(function() {
    /* Автозаполнитель поиска */

    $( ".fn_enhanced_search" ).devbridgeAutocomplete( {
        serviceUrl: okay.router['EnhancedProductsSearch.ajaxSearch'],
        minChars: 1,
        appendTo: "#fn_search",
        maxHeight: 320,
        noCache: true,
        crossDomain: true,
        onSearchStart: function(params) {
            ut_tracker.start('search_products');
        },
        onSearchComplete: function(params) {
            ut_tracker.end('search_products');
        },
        onSelect: function(suggestion) {
            $( "#fn_search" ).submit();
        },
        transformResult: function(result, query) {
            var data = JSON.parse(result);
            $(".fn_search").devbridgeAutocomplete('setOptions', {triggerSelectOnValidInput: data.suggestions.length == 1});
            return data;
        },
        formatResult: function(suggestion, currentValue) {
            var reEscape = new RegExp( '(\\' + ['/', '.', '*', '+', '?', '|', '(', ')', '[', ']', '{', '}', '\\'].join( '|\\' ) + ')', 'g' );
            var pattern = '(' + currentValue.replace( reEscape, '\\$1' ) + ')';
            return "<div>" + (suggestion.data.image ? "<img align='middle' src='" + suggestion.data.image + "'> " : '') + "</div>" + "<a href=" + suggestion.data.url + '>' + suggestion.value.replace( new RegExp( pattern, 'gi' ), '<strong>$1<\/strong>' ) + '<\/a>' + "<span>" + suggestion.price + " " + suggestion.currency + "</span>";
        }
    } );
});
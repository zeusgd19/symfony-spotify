$(document).ready(function (){
    if(window.location.pathname === '/'){
        window.history.pushState({}, '', '/');
    }

    if(sessionStorage.getItem('query')){
        let query = sessionStorage.getItem('query');
        window.history.pushState({}, '', '/search/' + query);
        if (query.length > 2) {
            $.ajax({
                url: '/query',
                type: 'POST',
                data: { q: query },
                success: function(response) {
                    const { artists, tracks } = response;
                    console.log(artists.items)
                    sessionStorage.clear()
                },
                error: function(xhr) {
                    console.error('Error en la búsqueda:', xhr.responseText);
                }
            });
        }
    }
})
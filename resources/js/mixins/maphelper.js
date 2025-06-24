
export default {
    methods: {
        //reverseGeocode
        getAddress(latitude, longitude, successCallBackHandler) {
            // const apiUrl = `https://maps.googleapis.com/maps/api/geocode/json?latlng=${latitude},${longitude}&key=${config.google_api_key}`;

            const apiUrl = `https://nominatim.openstreetmap.org/reverse?lat=${latitude}&lon=${longitude}&format=json`;

            fetch(apiUrl)
                .then(response => response.json())
                .then(data => {
                    // console.log('data',data)
                    const address = data.display_name;
                    successCallBackHandler(address);
                })
                .catch(error => {
                    console.log(error);
                });
        },

        // searchLocation(searchQuery,successCallBackHandler) {
        //     const apiUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(searchQuery)}`;
        //
        //     /*
        //     *
        //     * this returns a response in the format like
        //     * {
        //     *   'lat' => 11234244,
        //     *   'lon' => '2457677,
        //     *   'display_name' => 'Location Address',
        //     * }
        //     *
        //     *
        //     * */
        //
        //     fetch(apiUrl)
        //         .then(response => response.json())
        //         .then(data => successCallBackHandler(data))
        //         .catch(error => {
        //             console.error('Error fetching search results:', error);
        //         });
        // },

        // searchLocation(searchQuery, successCallBackHandler) {
        //     const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;
        //
        //     // use on development
        //     const apiUrl = `https://maps.googleapis.com/maps/api/geocode/json?address=${encodeURIComponent(searchQuery)}&key=${apiKey}`;
        //
        //     // use on production
        //     // const apiUrl = `https://maps.googleapis.com/maps/api/place/textsearch/json?query=${encodeURIComponent(searchQuery)}&key=${apiKey}`
        //
        //
        //     /*
        //     *
        //     * The response will be in the format like
        //     * {
        //     *   'lat' => 11234244,
        //     *   'lon' => '2457677,
        //     *   'display_name' => 'Location Address',
        //     * }
        //     *
        //     * */
        //
        //     fetch(apiUrl)
        //         .then(response => response.json())
        //         .then(data => {
        //             const formattedResults = data.results.map(result => {
        //                 const locationData = result.geometry.location;
        //                 const formattedAddress = result.formatted_address;
        //
        //                 return {
        //                     lat: locationData.lat,
        //                     lon: locationData.lng,
        //                     display_name: formattedAddress,
        //                 };
        //             });
        //
        //             successCallBackHandler(formattedResults);
        //         })
        //         .catch(error => {
        //             console.error('Error fetching search results:', error);
        //         });
        // },

        searchLocation(searchQuery, successCallBackHandler) {
            const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

            // Initialize the PlacesService from Google Maps JavaScript API
            const placesService = new google.maps.places.PlacesService(document.createElement('div'));

            // Create a PlacesService text search request object
            const request = {
                query: searchQuery,
            };

            // Use the PlacesService to perform a text search
            placesService.textSearch(request, (results, status) => {
                if (status === google.maps.places.PlacesServiceStatus.OK) {
                    const formattedResults = results.map(result => {
                        const locationData = result.geometry.location;
                        const formattedAddress = result.formatted_address;
                        const place = result.name;

                        return {
                            lat: locationData.lat(),
                            lon: locationData.lng(),
                            display_name: `${place} - ${formattedAddress}`,
                        };
                    });

                    successCallBackHandler(formattedResults);
                } else {
                    console.error('Places API text search request failed:', status);
                }
            });
        },



        calculateDistance(lat1, lon1, lat2, lon2){
            const R = 6371; // Earth's radius in kilometers

            const dLat = (lat2 - lat1) * (Math.PI / 180);
            const dLon = (lon2 - lon1) * (Math.PI / 180);

            const a =
                Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(lat1 * (Math.PI / 180)) * Math.cos(lat2 * (Math.PI / 180)) *
                Math.sin(dLon / 2) * Math.sin(dLon / 2);

            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

            // Distance in kilometers
            return R * c;
        }
    }
}

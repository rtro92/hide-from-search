document.addEventListener('DOMContentLoaded', function() {
    // Get the value of the 'custom_post_type' parameter from the URL
    const urlParams = new URLSearchParams(window.location.search);
    const customPostTypeParam = urlParams.get('custom_post_type');

    const app = new Vue({
        el: '#hfs_app',
        data: {
            value: customPostTypeParam ? customPostTypeParam : 'Select a Post Type', // Set the initial value based on the URL parameter
            list: hfs_data.cpts,
            visible: false
        },
        methods: {
            toggle() {
                this.visible = !this.visible;
            },
            select(key) {
                this.value = this.list[key]; // Update the displayed value if needed

                // Update the URL with the selected custom_post_type parameter and reload the page
                urlParams.set('custom_post_type', key); // Use the key as the URL parameter
                const newUrl = window.location.pathname + '?' + urlParams.toString();
                window.location.href = newUrl;
            }
        },
        computed: {
            displayValue() {
                return customPostTypeParam ? this.list[customPostTypeParam] : ''; // Set the relative value if custom_post_type parameter is set
            }
        }
    });
});

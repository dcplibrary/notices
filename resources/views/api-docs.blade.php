<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notices API Documentation</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.10.3/swagger-ui.css" />
    <script src="https://cdn.jsdelivr.net/npm/js-yaml@4.1.0/dist/js-yaml.min.js"></script>
    <style>
        html {
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
        }
        *, *:before, *:after {
            box-sizing: inherit;
        }
        body {
            margin:0;
            background: #fafafa;
        }
        .swagger-ui .topbar {
            background-color: #1f2937;
        }
        .swagger-ui .topbar .download-url-wrapper {
            display: none;
        }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.10.3/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.10.3/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            const openApiUrl = "{{ route('notices.api-docs.openapi') }}";
            
            // Fetch the YAML content and clean it before passing to Swagger UI
            fetch(openApiUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to fetch OpenAPI spec: ' + response.status);
                    }
                    return response.text();
                })
                .then(yamlContent => {
                    // Clean the YAML content - remove any leading whitespace
                    yamlContent = yamlContent.replace(/^[\s\n\r]+/, '');
                    
                    // Validate it starts correctly
                    if (!yamlContent.match(/^openapi:\s*3\.\d+\.\d+/)) {
                        throw new Error('Invalid OpenAPI format - file does not start with openapi: 3.x.x');
                    }
                    
                    // Parse YAML to JSON for Swagger UI (more reliable)
                    // Swagger UI can accept spec as object directly
                    try {
                        // Use js-yaml if available, otherwise try to parse manually
                        let spec;
                        if (typeof jsyaml !== 'undefined') {
                            spec = jsyaml.load(yamlContent);
                        } else {
                            // Fallback: try to use Swagger UI's built-in parser
                            // Pass the cleaned YAML string directly
                            spec = yamlContent;
                        }
                        
                        const ui = SwaggerUIBundle({
                            spec: typeof spec === 'object' ? spec : undefined,
                            url: typeof spec === 'object' ? undefined : openApiUrl,
                            validatorUrl: null,
                            dom_id: '#swagger-ui',
                            deepLinking: true,
                            presets: [
                                SwaggerUIBundle.presets.apis,
                                SwaggerUIStandalonePreset
                            ],
                            plugins: [
                                SwaggerUIBundle.plugins.DownloadUrl
                            ],
                            layout: "StandaloneLayout",
                            tryItOutEnabled: true,
                            onFailure: function(data) {
                                console.error('Failed to load OpenAPI spec:', data);
                                const errorMsg = data.err?.message || data.message || 'Unknown error';
                                document.getElementById('swagger-ui').innerHTML = 
                                    '<div style="padding: 20px; color: red; max-width: 800px; margin: 0 auto;">' +
                                    '<h2>Error Loading API Documentation</h2>' +
                                    '<p><strong>Error:</strong> ' + errorMsg + '</p>' +
                                    '<p><strong>URL:</strong> <a href="' + openApiUrl + '" target="_blank">' + openApiUrl + '</a></p>' +
                                    '<p>Please check browser console for details.</p>' +
                                    '</div>';
                            },
                            requestInterceptor: (request) => {
                                // Add authentication token if available
                                const token = localStorage.getItem('api_token');
                                if (token) {
                                    request.headers['Authorization'] = 'Bearer ' + token;
                                }
                                return request;
                            },
                            onComplete: () => {
                                // Add token input field
                                const authContainer = document.querySelector('.scheme-container');
                                if (authContainer) {
                                    const tokenInput = document.createElement('div');
                                    tokenInput.style.cssText = 'padding: 10px; background: #fafafa; border-bottom: 1px solid #ddd;';
                                    tokenInput.innerHTML = `
                                        <label style="font-weight: bold; margin-right: 10px;">API Token:</label>
                                        <input type="text" id="api-token-input" placeholder="Enter Bearer token" 
                                               style="width: 300px; padding: 5px; border: 1px solid #ccc; border-radius: 4px;"
                                               value="${localStorage.getItem('api_token') || ''}">
                                        <button onclick="saveToken()" style="margin-left: 10px; padding: 5px 15px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">Save Token</button>
                                        <button onclick="clearToken()" style="margin-left: 5px; padding: 5px 15px; background: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer;">Clear</button>
                                    `;
                                    authContainer.parentNode.insertBefore(tokenInput, authContainer);
                                }
                            }
                        });
                        
                        window.ui = ui;
                    } catch (e) {
                        console.error('Error parsing YAML:', e);
                        document.getElementById('swagger-ui').innerHTML = 
                            '<div style="padding: 20px; color: red;">' +
                            '<h2>Error Parsing OpenAPI Specification</h2>' +
                            '<p>' + e.message + '</p>' +
                            '</div>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching OpenAPI spec:', error);
                    document.getElementById('swagger-ui').innerHTML = 
                        '<div style="padding: 20px; color: red; max-width: 800px; margin: 0 auto;">' +
                        '<h2>Error Loading API Documentation</h2>' +
                        '<p><strong>Error:</strong> ' + error.message + '</p>' +
                        '<p><strong>URL:</strong> <a href="' + openApiUrl + '" target="_blank">' + openApiUrl + '</a></p>' +
                        '<p>Please check:</p>' +
                        '<ul>' +
                        '<li>File exists at: <code>docs/openapi.yaml</code></li>' +
                        '<li>Route is accessible (click URL above)</li>' +
                        '<li>File permissions are correct</li>' +
                        '</ul>' +
                        '</div>';
                });
        };
        
        function saveToken() {
            const token = document.getElementById('api-token-input').value;
            localStorage.setItem('api_token', token);
            alert('Token saved! It will be used for all API requests.');
            // Reload to apply token
            window.location.reload();
        }
        
        function clearToken() {
            localStorage.removeItem('api_token');
            document.getElementById('api-token-input').value = '';
            alert('Token cleared!');
        }
    </script>
</body>
</html>

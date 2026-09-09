console.log('Aiutoma Providers: Script injected successfully and running.');
(function() {
    var attempt = 0;

    function applyAiInstructions() {
        attempt++;
        var inputs = document.querySelectorAll('input, textarea');
        if (attempt % 5 === 1) { // Log every ~1 second instead of spamming 200ms
            console.log('Aiutoma Providers Polling (Attempt ' + attempt + '): Found ' + inputs.length + ' inputs.');
        }

        for (var i = 0; i < inputs.length; i++) {
            var input = inputs[i];

            // Find label text associated with this input
            var labelText = '';
            if (input.id) {
                var label = document.querySelector('label[for="' + input.id + '"]');
                if (label) {
                    labelText = (label.innerText || label.textContent).toLowerCase();
                }
            }

            // Gutenberg wraps inputs in components-base-control. Let's check parent text too.
            var parentText = '';
            var parent = input.closest ? input.closest('.components-base-control') : null;
            if (parent) {
                parentText = (parent.innerText || parent.textContent).toLowerCase();
            }

            var combinedText = labelText + ' ' + parentText;

            // Cloudflare Logic
            if (combinedText.indexOf('cloudflare') !== -1 && input.placeholder === 'Enter your API key') {
                if (!input.getAttribute('data-cf-forced')) {
                    console.log('Aiutoma Providers: Cloudflare input found!', input);
                    input.placeholder = 'ACCOUNT_ID|API_TOKEN';
                    var desc = document.createElement('p');
                    desc.className = 'description';
                    desc.style.marginTop = '4px';
                    desc.style.fontSize = '12px';
                    desc.innerHTML = 'For Cloudflare, please insert your Account ID and API Token separated by a pipe character (|). Example: <code>1234abcd|5678efgh</code>';
                    if (parent) parent.appendChild(desc);
                    else input.parentNode.insertBefore(desc, input.nextSibling);
                    input.setAttribute('data-cf-forced', '1');
                }
            }

            // AWS Logic
            if ((combinedText.indexOf('aws') !== -1 || combinedText.indexOf('amazon') !== -1) && input.placeholder === 'Enter your API key') {
                if (!input.getAttribute('data-aws-forced')) {
                    console.log('Aiutoma Providers: AWS input found!', input);
                    input.placeholder = 'ACCESS_KEY|SECRET_KEY|REGION';
                    var desc = document.createElement('p');
                    desc.className = 'description';
                    desc.style.marginTop = '4px';
                    desc.style.fontSize = '12px';
                    desc.innerHTML = 'You can retrieve your Amazon AWS AI credentials from the <a href="https://console.aws.amazon.com/iam/home?#/security_credentials" target="_blank">AWS IAM Console</a>. Please insert your Access Key, Secret Key, and Region separated by a pipe character (|). Example: <code>AKIAIOS...|wJalrXU...|us-east-1</code>';
                    
                    var select = document.createElement('select');
                    select.style.marginTop = '8px';
                    select.style.marginBottom = '8px';
                    select.style.maxWidth = '100%';
                    select.style.display = 'block';
                    
                    var regions = ['us-east-1 (N. Virginia - Default)', 'us-west-2 (Oregon)', 'eu-central-1 (Frankfurt)', 'eu-west-3 (Paris)', 'eu-west-2 (London)', 'ap-northeast-1 (Tokyo)', 'ap-southeast-1 (Singapore)'];
                    
                    var defOpt = document.createElement('option');
                    defOpt.value = '';
                    defOpt.innerText = '-- Select Region to append --';
                    select.appendChild(defOpt);

                    for(var r=0; r<regions.length; r++) {
                        var opt = document.createElement('option');
                        opt.value = regions[r].split(' ')[0];
                        opt.innerText = regions[r];
                        select.appendChild(opt);
                    }

                    select.addEventListener('change', function(e) {
                        var region = e.target.value;
                        if(!region) return;
                        
                        var currentVal = input.value || '';
                        var parts = currentVal.split('|');
                        
                        if (parts.length >= 2) {
                            parts[2] = region; // replace or add region
                        } else {
                            parts = [parts[0] || '', parts[1] || '', region];
                        }
                        
                        var newVal = parts.join('|');
                        
                        // React 16+ hack to trigger onChange properly
                        var nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, "value").set;
                        if (nativeInputValueSetter) {
                            nativeInputValueSetter.call(input, newVal);
                        } else {
                            input.value = newVal;
                        }
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                        
                        e.target.value = ''; // Reset select
                    });
                    
                    if (parent) {
                        parent.appendChild(desc);
                        parent.appendChild(select);
                    } else {
                        input.parentNode.insertBefore(desc, input.nextSibling);
                        input.parentNode.insertBefore(select, desc.nextSibling);
                    }
                    input.setAttribute('data-aws-forced', '1');
                }
            }
        }
    }

    // Run immediately and also on DOMContentLoaded
    applyAiInstructions();
    document.addEventListener('DOMContentLoaded', applyAiInstructions);

    // Polling for dynamically rendered components (React/Vue/AJAX)
    var checkInterval = setInterval(applyAiInstructions, 500);
    setTimeout(function() {
        console.log('Aiutoma Providers: Stopping initial polling after 60 seconds.');
        clearInterval(checkInterval);
    }, 60000); // Polling for 60 seconds to ensure it catches late React renders
    
    // Re-trigger when user clicks anywhere (like the "Setup" button)
    document.addEventListener('click', function() {
        // Check immediately and also after animation/rendering delay
        applyAiInstructions();
        setTimeout(applyAiInstructions, 100);
        setTimeout(applyAiInstructions, 500);
    });
})();


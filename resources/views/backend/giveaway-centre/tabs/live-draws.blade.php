<style>
    .live-draws-container {
        background: white;
        border-radius: 10px;
        padding: 2rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .draw-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 10px;
        margin-bottom: 2rem;
        text-align: center;
    }
    
    .draw-header h3 {
        color: white !important;
        margin: 0 0 0.5rem 0;
        font-size: 1.8rem;
    }
    
    .draw-header p {
        color: white !important;
        margin: 0;
        opacity: 0.9;
    }
    
    .draw-frame-wrapper {
        background: white;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        overflow: hidden;
        position: relative;
        min-height: 700px;
    }
    
    .draw-loading {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        z-index: 10;
    }
    
    .draw-loading.hidden {
        display: none;
    }
    
    .draw-spinner {
        width: 50px;
        height: 50px;
        border: 5px solid #f3f3f3;
        border-top: 5px solid #667eea;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .draw-loading-text {
        margin-top: 1rem;
        color: #666;
        font-weight: 500;
    }
    
    .draw-iframe {
        width: 100%;
        height: 700px;
        border: none;
        display: block;
    }
    
    .draw-info-box {
        background: #f8f9fa;
        border-left: 4px solid #667eea;
        padding: 1.5rem;
        margin-bottom: 2rem;
        border-radius: 5px;
    }
    
    .draw-info-box h4 {
        color: #667eea !important;
        margin: 0 0 1rem 0;
    }
    
    .draw-info-box ul {
        margin: 0;
        padding-left: 1.5rem;
    }
    
    .draw-info-box li {
        margin-bottom: 0.5rem;
        color: #666 !important;
    }
    
    .quick-actions {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }
    
    .quick-action-btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .quick-action-btn.primary {
        background: #667eea;
        color: white;
    }
    
    .quick-action-btn.primary:hover {
        background: #5568d3;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .quick-action-btn.secondary {
        background: #e9ecef;
        color: #495057;
    }
    
    .quick-action-btn.secondary:hover {
        background: #dee2e6;
    }
    
    .error-message {
        display: none;
        background: #fff5f5;
        border: 2px solid #feb2b2;
        border-radius: 10px;
        padding: 2rem;
        text-align: center;
        color: #c53030;
    }
    
    .error-message.show {
        display: block;
    }
    
    .error-message h4 {
        color: #c53030 !important;
        margin-bottom: 1rem;
    }
    
    .tab-inactive-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.95);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 5;
        cursor: pointer;
    }
    
    .tab-inactive-overlay.show {
        display: flex;
    }
    
    .tab-inactive-message {
        text-align: center;
        padding: 2rem;
    }
    
    .tab-inactive-message i {
        font-size: 4rem;
        color: #667eea;
        margin-bottom: 1rem;
    }
    
    .tab-inactive-message h4 {
        color: #333 !important;
        margin-bottom: 0.5rem;
    }
    
    .tab-inactive-message p {
        color: #666 !important;
        margin: 0;
    }
    
    @media (max-width: 768px) {
        .draw-iframe {
            height: 600px;
        }
        
        .draw-frame-wrapper {
            min-height: 600px;
        }
    }
</style>

<div class="live-draws-container">
    <!-- Header -->
    <div class="draw-header">
        <h3>🎬 Live Draw Viewer</h3>
        <p>Watch the latest giveaway winner announcement with live animation</p>
    </div>
    
    <!-- Info Box -->
    <div class="draw-info-box">
        <h4><i class="fe fe-info"></i> How It Works</h4>
        <ul>
            <li><strong>Latest Draw:</strong> Automatically shows the most recent winner announcement</li>
            <li><strong>Live Badge:</strong> Draws within 24 hours show a "🔴 LIVE" indicator</li>
            <li><strong>Replay:</strong> All past draws can be replayed anytime with full animation</li>
            <li><strong>Auto-Refresh:</strong> Page checks for new draws every 60 seconds</li>
            <li><strong>Public Link:</strong> This same view is available to customers at your public URL</li>
        </ul>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <button class="quick-action-btn primary" onclick="refreshLiveDraw()">
            <i class="fe fe-refresh-cw"></i> Refresh Draw
        </button>
        <button class="quick-action-btn secondary" onclick="openPublicLiveView()">
            <i class="fe fe-external-link"></i> Open Public View
        </button>
        <button class="quick-action-btn secondary" onclick="copyPublicDrawLink()">
            <i class="fe fe-copy"></i> Copy Public Link
        </button>
    </div>
    
    <!-- Draw Frame -->
    <div class="draw-frame-wrapper">
        <!-- Loading -->
        <div class="draw-loading" id="drawLoading">
            <div class="draw-spinner"></div>
            <div class="draw-loading-text">Loading latest draw...</div>
        </div>
        
        <!-- Error -->
        <div class="error-message" id="drawError">
            <h4>😕 No Draws Available</h4>
            <p>No giveaway draws have been completed yet.</p>
            <p>Draw a winner from the "Giveaways" tab to see the animation here!</p>
        </div>
        
        <!-- Tab Inactive Overlay -->
        <div class="tab-inactive-overlay" id="tabInactiveOverlay" onclick="resumeDrawPlayback()">
            <div class="tab-inactive-message">
                <i class="fe fe-play-circle"></i>
                <h4>Animation Paused</h4>
                <p>Click to resume playback</p>
            </div>
        </div>
        
        <!-- Iframe -->
        <iframe 
            id="drawIframe"
            class="draw-iframe"
            src="about:blank"
            title="Latest Giveaway Draw"
            allow="autoplay">
        </iframe>
    </div>
</div>

<script>
// Use vanilla JavaScript - no jQuery dependencies
(function() {
    'use strict';
    
    console.log('🎬 Live Draws Tab - Loaded (Vanilla JS - Tab Visibility Control)');
    
    var iframe = document.getElementById('drawIframe');
    var loading = document.getElementById('drawLoading');
    var error = document.getElementById('drawError');
    var overlay = document.getElementById('tabInactiveOverlay');
    var checkInterval = null;
    var currentDrawId = null;
    var isTabActive = false;
    var drawUrl = '{{ url("/public/draw/latest") }}';
    
    // Check if this tab is currently active
    function checkTabVisibility() {
        // Check if Live Draws tab link has 'active' class
        var liveDrawsTab = document.querySelector('a[href="#live-draws-tab"]');
        var isActive = liveDrawsTab && liveDrawsTab.classList.contains('active');
        
        if (isActive && !isTabActive) {
            console.log('✅ Live Draws tab activated');
            isTabActive = true;
            loadDrawIframe();
            if (overlay) overlay.classList.remove('show');
        } else if (!isActive && isTabActive) {
            console.log('⏸️ Live Draws tab deactivated - pausing iframe');
            isTabActive = false;
            pauseDrawIframe();
        }
        
        return isActive;
    }
    
    // Load the iframe
    function loadDrawIframe() {
        if (iframe && iframe.src === 'about:blank') {
            console.log('🎬 Loading draw iframe');
            iframe.src = drawUrl;
        }
    }
    
    // Pause the iframe by showing overlay
    function pauseDrawIframe() {
        if (overlay) {
            overlay.classList.add('show');
        }
    }
    
    // Resume playback
    window.resumeDrawPlayback = function() {
        if (overlay) {
            overlay.classList.remove('show');
        }
        // Reload iframe to restart animation
        if (iframe) {
            iframe.src = iframe.src;
        }
    };
    
    // Listen for tab changes
    document.addEventListener('click', function(e) {
        // Check if a tab was clicked
        if (e.target.closest('[data-bs-toggle="tab"]') || e.target.closest('a[href^="#"]')) {
            setTimeout(checkTabVisibility, 100);
        }
    });
    
    // Also check on hash change
    window.addEventListener('hashchange', function() {
        setTimeout(checkTabVisibility, 100);
    });
    
    // Initial check
    setTimeout(checkTabVisibility, 500);
    
    // Periodic check
    setInterval(checkTabVisibility, 1000);
    
    // Hide loading when iframe loads
    if (iframe) {
        iframe.addEventListener('load', function() {
            if (loading) {
                loading.classList.add('hidden');
            }
        });
        
        iframe.addEventListener('error', function() {
            if (loading) {
                loading.classList.add('hidden');
            }
            if (error) {
                error.classList.add('show');
            }
        });
    }
    
    // Global functions for buttons
    window.refreshLiveDraw = function() {
        if (loading) loading.classList.remove('hidden');
        if (error) error.classList.remove('show');
        if (iframe) iframe.src = drawUrl;
        
        // Show toast if available
        if (typeof toastr !== 'undefined') {
            toastr.info('Refreshing draw...');
        }
    };
    
    window.openPublicLiveView = function() {
        var url = window.location.origin + '/public/draw/latest';
        window.open(url, '_blank', 'width=1200,height=800');
    };
    
    window.copyPublicDrawLink = function() {
        var publicUrl = window.location.origin + '/public/draw/latest';
        
        // Create temporary input
        var temp = document.createElement('input');
        temp.value = publicUrl;
        document.body.appendChild(temp);
        temp.select();
        
        try {
            document.execCommand('copy');
            if (typeof toastr !== 'undefined') {
                toastr.success('Public link copied to clipboard!');
            } else {
                alert('Link copied: ' + publicUrl);
            }
        } catch (err) {
            alert('Copy failed. Link: ' + publicUrl);
        }
        
        document.body.removeChild(temp);
    };
    
    // Check for new draws using vanilla JS
    function checkForNewDraw() {
        // Only check if tab is active
        if (!isTabActive) {
            return;
        }
        
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/api/public/latest-draw-id', true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    
                    if (response.success && response.draw_id) {
                        if (currentDrawId === null) {
                            currentDrawId = response.draw_id;
                            console.log('✅ Current draw:', currentDrawId);
                        } else if (currentDrawId !== response.draw_id) {
                            console.log('🎉 New draw detected!');
                            currentDrawId = response.draw_id;
                            
                            // Show notification
                            if (typeof toastr !== 'undefined') {
                                toastr.info('New draw detected! Refreshing...', '', {
                                    timeOut: 3000,
                                    closeButton: true
                                });
                            }
                            
                            // Refresh iframe
                            window.refreshLiveDraw();
                        }
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                }
            }
        };
        
        xhr.onerror = function() {
            console.error('Error checking for new draws');
        };
        
        xhr.send();
    }
    
    // Start checking for new draws after 5 seconds
    setTimeout(function() {
        checkForNewDraw();
        checkInterval = setInterval(checkForNewDraw, 60000); // Every 60 seconds
    }, 5000);
    
    // Cleanup function
    window.cleanupLiveDraws = function() {
        if (checkInterval) {
            clearInterval(checkInterval);
            checkInterval = null;
        }
    };
    
})();
</script>
/**
 * Session-based animation control
 * Ensures header/footer only animate once per browser session
 */

(function() {
    'use strict';
    
    // Check if animations have already played in this session
    const animationsPlayed = sessionStorage.getItem('hub-animations-played');
    
    if (animationsPlayed === 'true') {
        // Skip animations - they've already played
        document.documentElement.classList.add('animations-played');
        document.body.classList.add('animations-played');
    } else {
        // Allow animations to play
        document.documentElement.classList.remove('animations-played');
        document.body.classList.remove('animations-played');
        
        // Mark as played after animations complete (1 second to be safe)
        setTimeout(() => {
            sessionStorage.setItem('hub-animations-played', 'true');
            document.body.classList.add('animations-played');
        }, 1000);
    }
})();

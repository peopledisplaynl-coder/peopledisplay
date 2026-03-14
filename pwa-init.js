/**
 * PeopleDisplay PWA Initialization
 * Registreert service worker en handelt installatie prompts af
 */

// Check of browser PWA support heeft
if ('serviceWorker' in navigator) {
  // Wacht tot pagina volledig geladen is
  window.addEventListener('load', () => {
    registerServiceWorker();
  });
}

// Registreer Service Worker
async function registerServiceWorker() {
  try {
    const registration = await navigator.serviceWorker.register('/service-worker.js', {
      scope: '/'
    });
    
    console.log('[PWA] Service Worker geregistreerd:', registration.scope);
    
    // Check voor updates
    registration.addEventListener('updatefound', () => {
      const newWorker = registration.installing;
      console.log('[PWA] Service Worker update gevonden');
      
      newWorker.addEventListener('statechange', () => {
        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
          // Nieuwe versie beschikbaar
          showUpdateNotification();
        }
      });
    });
    
    // Check periodiek voor updates (elk uur)
    setInterval(() => {
      registration.update();
    }, 60 * 60 * 1000);
    
  } catch (error) {
    console.error('[PWA] Service Worker registratie gefaald:', error);
  }
}

// Toon update notificatie
function showUpdateNotification() {
  // Alleen tonen als gebruiker is ingelogd (niet op login pagina)
  if (window.location.pathname === '/login.php') {
    return;
  }
  
  const notification = document.createElement('div');
  notification.id = 'pwa-update-notification';
  notification.innerHTML = `
    <div style="position: fixed; top: 20px; right: 20px; background: #4CAF50; color: white; 
                padding: 15px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                z-index: 10000; max-width: 300px; animation: slideIn 0.3s ease;">
      <strong>Nieuwe versie beschikbaar!</strong>
      <p style="margin: 10px 0; font-size: 14px;">Er is een update voor PeopleDisplay beschikbaar.</p>
      <button onclick="updateServiceWorker()" 
              style="background: white; color: #4CAF50; border: none; padding: 8px 16px; 
                     border-radius: 4px; cursor: pointer; font-weight: bold; margin-right: 10px;">
        Nu updaten
      </button>
      <button onclick="dismissUpdateNotification()" 
              style="background: transparent; color: white; border: 1px solid white; 
                     padding: 8px 16px; border-radius: 4px; cursor: pointer;">
        Later
      </button>
    </div>
  `;
  
  document.body.appendChild(notification);
}

// Update Service Worker
function updateServiceWorker() {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.ready.then((registration) => {
      registration.waiting?.postMessage({ type: 'SKIP_WAITING' });
      window.location.reload();
    });
  }
}

// Sluit update notificatie
function dismissUpdateNotification() {
  const notification = document.getElementById('pwa-update-notification');
  if (notification) {
    notification.remove();
  }
}

// Handle installatie prompt
let deferredPrompt;
let installButton;

window.addEventListener('beforeinstallprompt', (e) => {
  console.log('[PWA] Install prompt beschikbaar');
  
  // Prevent automatische prompt
  e.preventDefault();
  
  // Bewaar event voor later gebruik
  deferredPrompt = e;
  
  // Toon custom install button (alleen op frontpage en index)
  if (window.location.pathname === '/frontpage.php' || 
      window.location.pathname === '/index.php' || 
      window.location.pathname === '/') {
    showInstallButton();
  }
});

// Toon installatie button
function showInstallButton() {
  // Check of button al bestaat
  if (document.getElementById('pwa-install-button')) {
    return;
  }
  
  installButton = document.createElement('button');
  installButton.id = 'pwa-install-button';
  installButton.innerHTML = `
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
      <polyline points="7 10 12 15 17 10"></polyline>
      <line x1="12" y1="15" x2="12" y2="3"></line>
    </svg>
    Installeer App
  `;
  installButton.style.cssText = `
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: #4CAF50;
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 8px;
    z-index: 9999;
    transition: all 0.3s ease;
  `;
  
  installButton.addEventListener('mouseover', () => {
    installButton.style.transform = 'scale(1.05)';
    installButton.style.boxShadow = '0 6px 12px rgba(0,0,0,0.15)';
  });
  
  installButton.addEventListener('mouseout', () => {
    installButton.style.transform = 'scale(1)';
    installButton.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
  });
  
  installButton.addEventListener('click', async () => {
    if (!deferredPrompt) {
      return;
    }
    
    // Toon install prompt
    deferredPrompt.prompt();
    
    // Wacht op user choice
    const { outcome } = await deferredPrompt.userChoice;
    console.log('[PWA] User choice:', outcome);
    
    if (outcome === 'accepted') {
      console.log('[PWA] App geïnstalleerd');
    }
    
    // Clear prompt
    deferredPrompt = null;
    
    // Verwijder button
    installButton.remove();
  });
  
  document.body.appendChild(installButton);
}

// Detecteer of app als PWA draait
window.addEventListener('load', () => {
  const isStandalone = window.matchMedia('(display-mode: standalone)').matches || 
                       window.navigator.standalone === true;
  
  if (isStandalone) {
    console.log('[PWA] App draait als standalone');
    document.body.classList.add('pwa-standalone');
    
    // Verberg install button als die er is
    if (installButton) {
      installButton.remove();
    }
  }
});

// Handle app installed event
window.addEventListener('appinstalled', () => {
  console.log('[PWA] App succesvol geïnstalleerd');
  
  // Verwijder install button
  if (installButton) {
    installButton.remove();
  }
  
  // Clear deferred prompt
  deferredPrompt = null;
  
  // Optioneel: toon success message
  showInstallSuccessMessage();
});

// Toon installatie success bericht
function showInstallSuccessMessage() {
  const message = document.createElement('div');
  message.innerHTML = `
    <div style="position: fixed; top: 20px; right: 20px; background: #4CAF50; color: white; 
                padding: 15px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                z-index: 10000; animation: slideIn 0.3s ease;">
      <strong>✓ App geïnstalleerd!</strong>
      <p style="margin: 10px 0 0 0; font-size: 14px;">PeopleDisplay is nu beschikbaar op je home screen.</p>
    </div>
  `;
  
  document.body.appendChild(message);
  
  // Auto-remove na 5 seconden
  setTimeout(() => {
    message.remove();
  }, 5000);
}

// CSS animaties
const style = document.createElement('style');
style.textContent = `
  @keyframes slideIn {
    from {
      transform: translateX(400px);
      opacity: 0;
    }
    to {
      transform: translateX(0);
      opacity: 1;
    }
  }
  
  /* Standalone mode styling */
  .pwa-standalone {
    /* Extra padding voor standalone mode indien nodig */
  }
  
  /* iOS safe area support */
  @supports (padding: max(0px)) {
    .pwa-standalone {
      padding-top: max(20px, env(safe-area-inset-top));
      padding-bottom: max(20px, env(safe-area-inset-bottom));
      padding-left: max(20px, env(safe-area-inset-left));
      padding-right: max(20px, env(safe-area-inset-right));
    }
  }
`;
document.head.appendChild(style);

// Exporteer functies voor gebruik in andere scripts
window.PWA = {
  updateServiceWorker,
  dismissUpdateNotification,
  showInstallButton
};

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Prime Hashtag — Under Maintenance</title>
<meta name="robots" content="noindex">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=IBM+Plex+Mono:wght@400;500&display=swap');
 
  .ph-reset, .ph-reset * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }
 
  .ph-page {
    min-height: 100vh;
    background: #1E1B2E;
    background-image:
      radial-gradient(circle at 15% 20%, rgba(244,169,58,0.08), transparent 40%),
      radial-gradient(circle at 85% 80%, rgba(138,132,166,0.10), transparent 45%);
    color: #F5F1EA;
    font-family: 'Sora', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 6vh 24px;
  }
 
  .ph-card {
    width: 100%;
    max-width: 620px;
    text-align: center;
  }
 
  .ph-mark-wrap {
    display: flex;
    justify-content: center;
    margin-bottom: 40px;
    animation: ph-rise 0.9s cubic-bezier(0.16, 1, 0.3, 1) both;
  }
 
  .ph-mark {
    position: relative;
    width: 180px;
    height: 180px;
  }
 
  .ph-mark span {
    position: absolute;
    background: #F4A93A;
  }
 
  .ph-mark .ph-v1 { width: 14px; height: 180px; left: 52px; border-radius: 7px; }
  .ph-mark .ph-v2 { width: 14px; height: 180px; left: 114px; border-radius: 7px; background: #F5F1EA; opacity: 0.85; }
  .ph-mark .ph-h1 { height: 14px; width: 180px; top: 52px; border-radius: 7px; background: #F5F1EA; opacity: 0.85; }
  .ph-mark .ph-h2 { height: 14px; width: 180px; top: 114px; border-radius: 7px; }
 
  .ph-mark .ph-dot {
    position: absolute;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #8A84A6;
    top: 165px;
    left: 165px;
    animation: ph-pulse 2.2s ease-in-out infinite;
  }
 
  .ph-eyebrow {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 13px;
    letter-spacing: 0.02em;
    color: #F4A93A;
    margin-bottom: 18px;
    animation: ph-rise 0.9s cubic-bezier(0.16, 1, 0.3, 1) 0.1s both;
  }
 
  .ph-heading {
    font-size: clamp(28px, 5vw, 42px);
    font-weight: 800;
    line-height: 1.15;
    letter-spacing: -0.01em;
    margin-bottom: 18px;
    animation: ph-rise 0.9s cubic-bezier(0.16, 1, 0.3, 1) 0.18s both;
  }
 
  .ph-body {
    font-size: 16px;
    line-height: 1.65;
    color: #C7C2DA;
    max-width: 440px;
    margin: 0 auto 36px;
    animation: ph-rise 0.9s cubic-bezier(0.16, 1, 0.3, 1) 0.26s both;
  }
 
  .ph-status {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 13px;
    color: #F5F1EA;
    background: rgba(245, 241, 234, 0.06);
    border: 1px solid rgba(245, 241, 234, 0.14);
    padding: 10px 18px;
    border-radius: 100px;
    margin-bottom: 44px;
    animation: ph-rise 0.9s cubic-bezier(0.16, 1, 0.3, 1) 0.34s both;
  }
 
  .ph-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #F4A93A;
    animation: ph-pulse 2.2s ease-in-out infinite;
  }
 
  .ph-divider {
    width: 48px;
    height: 1px;
    background: rgba(245, 241, 234, 0.18);
    margin: 0 auto 32px;
  }
 
  .ph-contact {
    animation: ph-rise 0.9s cubic-bezier(0.16, 1, 0.3, 1) 0.4s both;
  }
 
  .ph-contact-label {
    font-size: 13px;
    color: #8A84A6;
    margin-bottom: 10px;
  }
 
  .ph-contact-link {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 15px;
    color: #F5F1EA;
    text-decoration: none;
    border-bottom: 1px solid rgba(244, 169, 58, 0.5);
    padding-bottom: 2px;
    transition: border-color 0.2s ease, color 0.2s ease;
  }
 
  .ph-contact-link:hover {
    color: #F4A93A;
    border-color: #F4A93A;
  }
 
  .ph-footer {
    margin-top: 56px;
    font-size: 12px;
    color: #55506C;
    letter-spacing: 0.02em;
  }
 
  @keyframes ph-rise {
    from { opacity: 0; transform: translateY(14px); }
    to { opacity: 1; transform: translateY(0); }
  }
 
  @keyframes ph-pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.8); }
  }
 
  @media (prefers-reduced-motion: reduce) {
    .ph-mark-wrap, .ph-eyebrow, .ph-heading, .ph-body, .ph-status, .ph-contact,
    .ph-status-dot, .ph-mark .ph-dot {
      animation: none !important;
    }
  }
 
  @media (max-width: 480px) {
    .ph-mark { width: 140px; height: 140px; }
    .ph-mark .ph-v1 { left: 40px; height: 140px; }
    .ph-mark .ph-v2 { left: 88px; height: 140px; }
    .ph-mark .ph-h1 { top: 40px; width: 140px; }
    .ph-mark .ph-h2 { top: 88px; width: 140px; }
    .ph-mark .ph-dot { top: 128px; left: 128px; }
  }
</style>
</head>
<body class="ph-reset">
  <div class="ph-page">
    <div class="ph-card">
 
  
 
      <div class="ph-eyebrow">prime hashtag</div>
 
      <h1 class="ph-heading">We're tightening a few bolts.</h1>
 
      <p class="ph-body">
        Our site is offline for scheduled maintenance right now. We're making things faster
        and cleaner — thanks for your patience, we'll be back shortly.
      </p>
 
      <div class="ph-status">
        <span class="ph-status-dot"></span>
        Maintenance in progress
      </div>
 
      <div class="ph-divider"></div>
 
      <div class="ph-contact">
        <div class="ph-contact-label">Need us urgently? Reach out here</div>
        <a class="ph-contact-link" href="mailto:sales@primehashtag.com">sales@primehashtag.com</a>
      </div>
 
      <div class="ph-footer">© 2026 Prime Hashtag. All rights reserved.</div>
 
    </div>
  </div>
</body>
</html>
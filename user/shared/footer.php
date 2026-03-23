
<style>
  @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500&display=swap');
  .ft-root{font-family:'DM Sans',sans-serif;background:#111110;color:#a09f9a;padding:4rem 1.5rem 2rem;box-sizing:border-box}
  .ft-inner{max-width:1200px;margin:0 auto}
  .ft-grid{display:grid;grid-template-columns:1.6fr 1fr 1fr 1.4fr;gap:3rem;margin-bottom:3.5rem}
  .ft-brand-name{font-family:'DM Serif Display',serif;font-size:1.5rem;color:#f0ede6;display:flex;align-items:center;gap:0.6rem;margin-bottom:1rem}
  .ft-brand-name svg{color:#d4a847}
  .ft-brand-desc{font-size:0.875rem;line-height:1.75;color:#6b6a65}
  .ft-socials{display:flex;gap:0.6rem;margin-top:1.5rem}
  .ft-social-btn{width:36px;height:36px;border-radius:8px;border:0.5px solid #2a2a27;display:flex;align-items:center;justify-content:center;color:#6b6a65;text-decoration:none;transition:border-color 0.2s,color 0.2s,background 0.2s}
  .ft-social-btn:hover{border-color:#d4a847;color:#d4a847;background:rgba(212,168,71,0.07)}
  .ft-col-head{font-size:0.7rem;letter-spacing:0.12em;text-transform:uppercase;color:#6b6a65;margin-bottom:1.25rem;font-weight:500}
  .ft-links{list-style:none;padding:0;margin:0}
  .ft-links li{margin-bottom:0.6rem}
  .ft-links a{color:#8a8980;text-decoration:none;font-size:0.875rem;display:flex;align-items:center;gap:0.4rem;transition:color 0.2s}
  .ft-links a:hover{color:#f0ede6}
  .ft-links a:hover .ft-arrow{transform:translateX(3px)}
  .ft-arrow{display:inline-block;transition:transform 0.2s;opacity:0.4;font-size:0.7rem}
  .ft-sub-label{font-size:0.8rem;color:#6b6a65;margin-bottom:1rem;line-height:1.6}
  .ft-sub-form{display:flex;flex-direction:column;gap:0.5rem}
  .ft-sub-input{background:#1a1a18;border:0.5px solid #2a2a27;border-radius:8px;padding:0.7rem 1rem;color:#f0ede6;font-size:0.875rem;font-family:inherit;outline:none;transition:border-color 0.2s}
  .ft-sub-input::placeholder{color:#3d3d39}
  .ft-sub-input:focus{border-color:#d4a847}
  .ft-sub-btn{background:linear-gradient(135deg,#d4a847,#b8873a);border:none;border-radius:8px;padding:0.7rem 1rem;color:#1a1008;font-size:0.8rem;font-weight:500;font-family:inherit;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.4rem;transition:opacity 0.2s,transform 0.15s}
  .ft-sub-btn:hover{opacity:0.9;transform:translateY(-1px)}
  .ft-divider{height:0.5px;background:#1e1e1b;margin-bottom:2rem}
  .ft-bottom{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem}
  .ft-copy{font-size:0.8rem;color:#4a4a46}
  .ft-copy .heart{color:#c0392b;display:inline-block;animation:hb 1.8s ease-in-out infinite}
  @keyframes hb{0%,100%{transform:scale(1)}50%{transform:scale(1.25)}}
  .ft-bottom-links{display:flex;gap:1.5rem}
  .ft-bottom-links a{font-size:0.78rem;color:#4a4a46;text-decoration:none;display:flex;align-items:center;gap:0.3rem;transition:color 0.2s}
  .ft-bottom-links a:hover{color:#8a8980}
  @media(max-width:860px){.ft-grid{grid-template-columns:1fr 1fr;gap:2rem}}
  @media(max-width:520px){.ft-grid{grid-template-columns:1fr}}
</style>

<div class="ft-root">
  <div class="ft-inner">
    <div class="ft-grid">

      <!-- Brand -->
      <div>
        <div class="ft-brand-name">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
          StudentsHub
        </div>
        <p class="ft-brand-desc">A space for students to learn about finance, innovation, technology, skills, and opportunities. Join our community and grow together.</p>
        <div class="ft-socials">
          <a class="ft-social-btn" href="#" title="Twitter">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.748l7.73-8.835L1.254 2.25H8.08l4.253 5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
          </a>
          <a class="ft-social-btn" href="#" title="Facebook">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
          </a>
          <a class="ft-social-btn" href="#" title="Instagram">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
          </a>
          <a class="ft-social-btn" href="#" title="LinkedIn">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
          </a>
        </div>
      </div>

      <!-- Quick Links -->
      <div>
        <div class="ft-col-head">Navigation</div>
        <ul class="ft-links">
          <li><a href="index.php"><span class="ft-arrow">›</span> Home</a></li>
          <li><a href="explore.php"><span class="ft-arrow">›</span> Explore</a></li>
          <li><a href="about.php"><span class="ft-arrow">›</span> About us</a></li>
          <li><a href="contact.php"><span class="ft-arrow">›</span> Contact</a></li>
        </ul>
      </div>

      <!-- Resources -->
      <div>
        <div class="ft-col-head">Resources</div>
        <ul class="ft-links">
          <li><a href="privacy.php"><span class="ft-arrow">›</span> Privacy policy</a></li>
          <li><a href="terms.php"><span class="ft-arrow">›</span> Terms of service</a></li>
          <li><a href="cookies.php"><span class="ft-arrow">›</span> Cookie policy</a></li>
        </ul>
      </div>

      <!-- Newsletter -->
      <div>
        <div class="ft-col-head">Stay updated</div>
        <p class="ft-sub-label">Get the latest posts and student opportunities delivered straight to your inbox.</p>
        <div class="ft-sub-form">
          <input class="ft-sub-input" type="email" placeholder="your@email.com" />
          <button class="ft-sub-btn">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Subscribe
          </button>
        </div>
      </div>

    </div>

    <div class="ft-divider"></div>

    <div class="ft-bottom">
      <p class="ft-copy">
        &copy; 2025 StudentsHub. All rights reserved.
        &nbsp;·&nbsp; Made with <span class="heart">♥</span> for students everywhere
      </p>
      <div class="ft-bottom-links">
        <a href="#">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Security
        </a>
        <a href="#">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
          API
        </a>
        <a href="#">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          Help
        </a>
      </div>
    </div>

  </div>
</div>

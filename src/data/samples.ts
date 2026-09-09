export interface SamplePage {
  id: string;
  name: string;
  description: string;
  url: string;
  html: string;
}

export const SAMPLES: SamplePage[] = [
  {
    id: "series_episodes",
    name: "Series Episodes (Multi-Link)",
    description: "Contains multiple episode action buttons (Ep 1 to 6) for testing alternating button generator.",
    url: "https://animexstream.example/series/season-1",
    html: `<!DOCTYPE html>
<html>
<head><title>Season 1 Episodes Stream</title></head>
<body>
  <header><nav><a href="https://animexstream.example">Home</a></nav></header>
  <main class="episode-list">
    <h1>Season 1 - Full Episode Links</h1>
    <div class="episodes-grid">
      <a class="btn-download" href="https://stream.example/watch/s1-ep01">Watch Episode 01 HD</a>
      <a class="btn-download" href="https://stream.example/watch/s1-ep02">Watch Episode 02 HD</a>
      <a class="btn-download" href="https://stream.example/watch/s1-ep03">Watch Episode 03 HD</a>
      <a class="btn-download" href="https://stream.example/watch/s1-ep04">Watch Episode 04 HD</a>
      <a class="btn-download" href="https://stream.example/watch/s1-ep05">Watch Episode 05 HD</a>
      <a class="btn-download" href="https://stream.example/watch/s1-ep06">Watch Episode 06 HD</a>
    </div>
  </main>
  <footer><p>&copy; 2026 AnimeXStream</p></footer>
</body>
</html>`
  },
  {
    id: "streaming",
    name: "Media Streaming Portal",
    description: "Contains server buttons, download mirrors, and nav items to ignore.",
    url: "https://media-stream-hub.example/watch/episode-101",
    html: `<!DOCTYPE html>
<html>
<head><title>Watch Episode 101</title></head>
<body>
  <!-- Header to be excluded by Python ElementCollector -->
  <header class="site-header">
    <div class="logo">StreamHub</div>
    <nav class="main-menu">
      <a href="https://media-stream-hub.example/home">Home</a>
      <a href="https://media-stream-hub.example/categories">Categories</a>
      <a href="https://media-stream-hub.example/privacy-policy">Privacy Policy</a>
    </nav>
  </header>

  <main class="content-area">
    <div class="video-container">
      <h2>Episode 101 - The Adventure Begins</h2>
      <div class="server-selectors">
        <button class="btn btn-server" onclick="window.location='https://filemoon.sx/e/ab12cd34'">Server 1 (Filemoon)</button>
        <button class="btn btn-server" data-url="https://streamtape.com/v/99887766">Server 2 (Streamtape)</button>
        <a class="action-btn download-link" href="https://fastdirect.download/file/ep101-1080p.mkv">Direct Download 1080p</a>
        <a class="btn mirror" href="https://doodstream.com/d/xyz890">Backup Mirror (DoodStream)</a>
      </div>
    </div>
  </main>

  <!-- Footer to be excluded by Python ElementCollector -->
  <footer class="site-footer">
    <p>&copy; 2026 StreamHub. <a href="https://media-stream-hub.example/contact">Contact</a></p>
  </footer>
</body>
</html>`
  },
  {
    id: "software",
    name: "Software Download Site",
    description: "Tested with installer buttons, GitHub releases, and sidebar links.",
    url: "https://open-tools.example/app/installer",
    html: `<!DOCTYPE html>
<html>
<head><title>OpenTools Downloader</title></head>
<body>
  <div class="topbar">
    <a href="https://open-tools.example/about">About</a>
    <a href="https://open-tools.example/login">Sign In</a>
  </div>
  <div class="page-body">
    <h1>SuperUtility v2.4</h1>
    <p>High speed developer utility package for Linux and macOS.</p>
    
    <div class="download-box">
      <a class="btn btn-primary download" href="https://github.com/opentools/releases/download/v2.4/installer.tar.gz">Download Package (v2.4)</a>
      <button class="btn btn-secondary" formaction="https://mirror.us-west.cloud/repo/installer.tar.gz">Direct Cloud Mirror (.tar.gz)</button>
      <input type="button" class="btn" value="Get Source Code" onclick="window.open('https://gitlab.org/opentools/core')" />
    </div>

    <!-- Internal navigation that should be excluded -->
    <div class="category-list">
      <a href="https://open-tools.example/archive">Old Versions Archive</a>
    </div>
  </div>
  <div class="footer_area">
    <a href="https://open-tools.example/cookie-policy">Cookies</a>
  </div>
</body>
</html>`
  },
  {
    id: "live_example",
    name: "Live Webpage (Example.com)",
    description: "Standard live HTTP test to https://example.com",
    url: "https://example.com",
    html: ""
  }
];

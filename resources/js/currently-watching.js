class CurrentlyWatching extends HTMLElement {
  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
  }

  connectedCallback() {
    this.render();
    this.fetchData();
  }

  render() {
    this.shadowRoot.innerHTML = `
      <style>
        :host {
          display: block;
        }

        .section {
          padding: 3rem 1.5rem;
          display: flex;
          align-items: center;
        }

        .container {
          width: 100%;
        }

        h2 {
          margin-bottom: 1rem;
          font-size: 2rem;
          font-weight: 700;
        }

        .card {
          border: 1px solid #BD5D38;
          border-radius: 0.25rem;
          background: white;
        }

        .card-body {
          padding: 1.25rem;
        }

        .badge {
          display: inline-block;
          padding: 0.25rem 0.5rem;
          font-size: 0.75rem;
          font-weight: 700;
          line-height: 1;
          text-align: center;
          white-space: nowrap;
          vertical-align: baseline;
          border-radius: 0.25rem;
          background-color: #BD5D38;
          color: white;
          margin-bottom: 0.5rem;
        }

        .card-title {
          margin-bottom: 0.5rem;
          font-size: 1.25rem;
          font-weight: 500;
        }

        .card-text {
          color: #6c757d;
          margin-bottom: 0;
        }

        .card-text small {
          font-size: 0.875rem;
        }

        .loading {
          text-align: center;
          padding: 1rem;
          color: #6c757d;
        }

        hr {
          margin: 0;
          border: 0;
          border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        @media (min-width: 992px) {
          .section {
            padding: 3rem 3rem;
          }
        }
      </style>

      <section class="section">
        <div class="container">
          <h2>Currently Watching</h2>
          <div class="card">
            <div class="card-body">
              <div class="content">
                <div class="loading">Loading...</div>
              </div>
            </div>
          </div>
        </div>
      </section>
      <hr>
    `;
  }

  async fetchData() {
    try {
      const response = await fetch('/api/currently-watching');
      const data = await response.json();

      const content = this.shadowRoot.querySelector('.content');

      if (!data) {
        this.style.display = 'none';
        return;
      }

      const isMusic = data.media_type === 'Album' || data.media_type === 'Song';
      let title = data.title;
      let subtitle = null;

      if (isMusic) {
        // For music, format as "Artist - Song"
        if (data.artist_name) {
          title = data.artist_name;
          if (data.title) {
            title += ` - ${data.title}`;
          }
        }
        // Show album as subtitle
        if (data.album_name) {
          subtitle = data.album_name;
        }
      } else if (data.series_name) {
        title = `${data.series_name}`;
        if (data.season_number && data.episode_number) {
          title += ` S${String(data.season_number).padStart(2, '0')}E${String(data.episode_number).padStart(2, '0')}`;
        }
        if (data.title) {
          title += ` - ${data.title}`;
        }
      } else if (data.year) {
        title += ` (${data.year})`;
      }

      const mediaType = this.formatMediaType(data.media_type);
      const lastWatched = data.last_watched || 'recently';
      const header = this.getHeader(data.event_type, isMusic);
      const timestampLabel = this.getTimestampLabel(data.event_type, isMusic);

      // Update the section header
      const sectionHeader = this.shadowRoot.querySelector('h2');
      sectionHeader.textContent = header;

      content.innerHTML = `
        <span class="badge">${mediaType}</span>
        <h5 class="card-title">${this.escapeHtml(title)}</h5>
        ${subtitle ? `<p class="card-text" style="margin-bottom: 0.5rem;"><small style="color: #6c757d;">${this.escapeHtml(subtitle)}</small></p>` : ''}
        <p class="card-text">
          <small>${this.escapeHtml(timestampLabel)} ${this.escapeHtml(lastWatched)}</small>
        </p>
      `;

      this.style.display = 'block';
    } catch (error) {
      console.error('Error fetching currently watching:', error);
      this.style.display = 'none';
    }
  }

  formatMediaType(type) {
    if (!type) return 'Media';

    const typeMap = {
      'Movie': 'Movie',
      'Episode': 'TV Show',
      'Season': 'TV Season',
      'Series': 'TV Series',
      'Album': 'Album',
      'Song': 'Song',
      'Video': 'Video',
    };

    return typeMap[type] || type;
  }

  getHeader(eventType, isMusic = false) {
    if (!eventType) {
      return isMusic ? 'Currently Listening' : 'Currently Watching';
    }

    switch (eventType) {
      case 'PlaybackStart':
      case 'PlaybackProgress':
        return isMusic ? 'Currently Listening' : 'Currently Watching';
      case 'PlaybackStop':
        return isMusic ? 'Last Listened' : 'Last Watched';
      case 'ItemAdded':
        return 'Media Added';
      case 'ItemDeleted':
        return 'Media Deleted';
      default:
        return isMusic ? 'Currently Listening' : 'Currently Watching';
    }
  }

  getTimestampLabel(eventType, isMusic = false) {
    if (!eventType) {
      return 'Last updated';
    }

    switch (eventType) {
      case 'PlaybackStop':
        return isMusic ? 'Last listened' : 'Last watched';
      case 'ItemAdded':
        return 'Added';
      case 'ItemDeleted':
        return 'Deleted';
      default:
        return 'Last updated';
    }
  }

  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
}

customElements.define('currently-watching', CurrentlyWatching);

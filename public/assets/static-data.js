'use strict';
// Public data only. Authentication takes place on github.com, never here.
window.staticNews = {
  pending: null,
  async data() {
    if (!this.pending) this.pending = fetch('data/news.json').then(r => {
      if (!r.ok) throw Error('Nachrichtendaten konnten nicht geladen werden.');
      return r.json();
    }).catch(e => { this.pending = null; throw e; });
    return this.pending;
  },
  async query(params) {
    const data = await this.data();
    const codes = params.get('regions').split(',').filter(c => c in data.regions);
    const q = params.get('q').toLocaleLowerCase('de');
    const days = Number(params.get('days'));
    const cutoff = days ? Date.now() / 1000 - days * 86400 : 0;
    const counts = Object.fromEntries(Object.keys(data.regions).map(c => [c, 0]));
    for (const a of data.articles) for (const c of a.regions) counts[c]++;
    const items = data.articles.filter(a => a.published >= cutoff &&
      (!codes.length || a.regions.some(c => codes.includes(c))) &&
      `${a.title} ${a.summary} ${a.source}`.toLocaleLowerCase('de').includes(q));
    const page = Math.min(Math.max(1, Number(params.get('page')) || 1), Math.max(1, Math.ceil(items.length / 20)));
    return {...data, counts, total: items.length, page, articles: items.slice((page - 1) * 20, page * 20)};
  },
  proposal(article, codes) {
    const values = [...new Set(codes)].sort();
    if (!values.length) throw Error('Bitte mindestens eine Region auswählen.');
    if (values.length > 1 && (values.includes('DE') || values.includes('UNK')))
      throw Error('„Bundesweit“ und „Ungeklärt“ bitte jeweils allein auswählen.');
    const before = [...article.regions].sort();
    if (JSON.stringify(before) === JSON.stringify(values)) throw Error('Die Zuordnung ist unverändert.');
    return {version: 1, article_id: article.id, previous_regions: before, regions: values};
  },
  issueURL(repository, proposal) {
    if (!/^[\w.-]+\/[\w.-]+$/.test(repository)) throw Error('Ungültiges Repository.');
    const url = new URL(`https://github.com/${repository}/issues/new`);
    url.searchParams.set('title', `Zuordnung: ${proposal.article_id.slice(0, 12)} → ${proposal.regions.join(', ')}`);
    url.searchParams.set('body', '```json\n' + JSON.stringify(proposal, null, 2) + '\n```');
    url.searchParams.set('template', 'category-correction.md');
    return url.href;
  }
};

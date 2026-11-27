"""Read publisher-provided descriptions, without generating or shortening text."""
import re
from html.parser import HTMLParser

class Metadata(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.values = {}
        self.title = ''
        self.in_title = False
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'title': self.in_title = True
        if tag == 'meta':
            key = (attrs.get('property') or attrs.get('name') or '').lower()
            self.values.setdefault(key, attrs.get('content', ''))
    def handle_endtag(self, tag):
        if tag == 'title': self.in_title = False
    def handle_data(self, data):
        if self.in_title: self.title += data

def extract_teaser(html, expected_title):
    metadata = Metadata()
    metadata.feed(html)
    # A redirect to a homepage or consent page must not supply the teaser.
    tokens = lambda s: set(re.findall(r'\w{4,}', s.casefold()))
    expected = tokens(expected_title)
    actual = tokens(metadata.values.get('og:title') or metadata.title)
    if not expected or len(expected & actual) / len(expected) < 0.5:
        return {}
    for key in ('og:description', 'description', 'twitter:description'):
        value = ' '.join(metadata.values.get(key, '').split())
        if 20 <= len(value) <= 5000 and value.casefold() != expected_title.casefold():
            return {'summary': value, 'summary_source': key}
    return {}

import absUrl from '../../../composables/absUrl';

describe('absUrl', () => {
  // Legacy stock-image paths were stored root-relative; on a sub-directory
  // install they must be resolved against wwwroot (#459/#460).
  it('prefixes a root-relative path with wwwroot', () => {
    expect(absUrl('/local/adele/public/lp_default/a.png', 'https://moo.test/moodle03'))
      .toBe('https://moo.test/moodle03/local/adele/public/lp_default/a.png');
  });

  it('leaves absolute URLs untouched (uploaded pluginfile images)', () => {
    const url = 'https://moo.test/moodle03/pluginfile.php/1/local_adele/lp_images/0/x.png';
    expect(absUrl(url, 'https://moo.test/moodle03')).toBe(url);
  });

  it('leaves protocol-relative URLs untouched', () => {
    expect(absUrl('//cdn.test/a.png', 'https://moo.test')).toBe('//cdn.test/a.png');
  });

  it('passes through empty and non-string values unchanged', () => {
    expect(absUrl('', 'https://moo.test')).toBe('');
    expect(absUrl(null, 'https://moo.test')).toBeNull();
    expect(absUrl(undefined, 'https://moo.test')).toBeUndefined();
  });

  it('survives a missing wwwroot (root install fallback)', () => {
    expect(absUrl('/local/adele/x.png', '')).toBe('/local/adele/x.png');
  });
});

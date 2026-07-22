import { viewportKey, saveViewport, loadViewport } from '../../../../composables/flowHelper/viewportStorage';

// #485: the student's VueFlow viewport is persisted client-side (core/localstorage) per
// learning-path + user, so a genuine page reload restores their place. These pure helpers
// are unit-tested with an in-memory storage stub matching the core/localstorage API.
const makeStorage = () => {
  const data = {};
  return {
    get: jest.fn((k) => (k in data ? data[k] : false)),
    set: jest.fn((k, v) => { data[k] = v; }),
  };
};

describe('viewportStorage (#485)', () => {
  it('builds a per learning-path + user key', () => {
    expect(viewportKey(7, 42)).toBe('local_adele/viewport/7/42');
  });

  it('round-trips a viewport (pan + zoom)', () => {
    const storage = makeStorage();
    const key = viewportKey(7, 42);
    saveViewport(storage, key, { x: 10, y: -20, zoom: 0.5, extra: 'ignored' });
    // Only x/y/zoom are stored.
    expect(loadViewport(storage, key)).toEqual({ x: 10, y: -20, zoom: 0.5 });
  });

  it('returns null when nothing is saved', () => {
    expect(loadViewport(makeStorage(), 'missing')).toBeNull();
  });

  it('ignores a corrupt or partial stored value', () => {
    const storage = makeStorage();
    storage.set('bad', '{not valid json');
    expect(loadViewport(storage, 'bad')).toBeNull();
    storage.set('partial', JSON.stringify({ x: 1 })); // no y / zoom
    expect(loadViewport(storage, 'partial')).toBeNull();
  });

  it('does not persist an invalid viewport, and never throws on write failure', () => {
    const noop = makeStorage();
    saveViewport(noop, 'k', null);
    expect(noop.set).not.toHaveBeenCalled();

    const failing = { get: () => false, set: () => { throw new Error('quota'); } };
    expect(() => saveViewport(failing, 'k', { x: 1, y: 2, zoom: 1 })).not.toThrow();
  });
});

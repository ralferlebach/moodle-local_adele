// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Page-unique DOM ids of the dialogs and their references (local_adele #18).
 *
 * Two applications (two stores with their own prefix) are mounted side by
 * side, as on a course page with two ADELE activities: no id twice, every
 * label/aria/data-target reference names an element of its own application.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import ModalNode from '../../../components/modals/ModalNode.vue';
import ModalCourse from '../../../components/modals/ModalCourse.vue';
import HelpingSlider from '../../../components/modals/HelpingSlider.vue';
import CustomNode from '../../../components/nodes/CustomNode.vue';
import LearningModule from '../../../components/flowchart/LearningModule.vue';
import ScopedEdge from '../../../components/edges/ScopedEdge.vue';
import {
  appDomId, appDomSelector, nextAppDomPrefix, scopedMarker, scopeMarkers, DIALOGS,
} from '../../../composables/domIds';

const strings = {
  modals_close: 'Close',
  modals_edit: 'Edit',
  modals_longname: 'Long name',
  modals_description: 'Description',
  estimate_duration: 'Estimated duration',
  edit_course_node: 'Edit node',
  edit: 'Edit',
  nodes_collection: 'Collection',
  flowchart_title: 'Title',
  flowchart_color: 'Colour',
  tutorial_title: 'Tutorial',
};

/**
 * A store as main.js makes it for one application.
 *
 * @param {string} domPrefix The application's prefix.
 * @returns {object}
 */
const makeStore = (domPrefix) => createStore({
  state: {
    domPrefix,
    strings,
    view: 'manager',
    version: '2024010100',
    wwwroot: 'https://example.test',
    availablecourses: [],
    node: { node_id: 'dndnode_1', fullname: 'Node A', imagepaths: {} },
    lpimages: { helpingslider: [], node_background_image: [] },
    learningpath: { image: '', json: { tree: { nodes: [] } } },
    lpuserpathrelation: { image: '' },
    feedbacksettings: { show_feedback: false, show_info: false },
  },
  mutations: { updatedNode() {} },
  actions: { saveLearningpath() {} },
});

/** The props of the learning-module panel: one module to edit. */
const moduleProps = () => ({
  learningmodule: { json: { modules: [{ id: 1, name: 'Module 1', color: '#000000' }] } },
  strings: { LIGHT_SEA_GREEN: '#20b2aa', PUMPKIN: '#ff7518', CRIMSON: '#dc143c' },
});

const HandleStub = { props: ['id', 'type', 'position'], template: '<div class="vue-flow__handle" :data-handleid="id" />' };

const learningpath = { json: { tree: { nodes: [{ id: 'node-1', parentCourse: ['starting_node'], restriction: { nodes: [] } }] } } };

/**
 * Mount the editor's dialogs and a node card of one application into the
 * same document.
 *
 * @param {object} store The application's store.
 * @param {HTMLElement} host Where to mount.
 * @returns {Array} The wrappers.
 */
const mountApplication = (store, host) => {
  const global = {
    plugins: [store],
    // VueFlow's Handle takes "id" as the handle's name, not as a DOM id (the
    // E2E scan of the real page confirms it); a plain stub would write it out.
    stubs: { Handle: HandleStub, NodeInformation: true, UserInformation: true, MasterConditions: true },
    directives: { tooltip: {} },
  };
  const attach = () => {
    const el = document.createElement('div');
    host.appendChild(el);
    return el;
  };
  return [
    mount(ModalNode, { props: { learningpath }, global, attachTo: attach() }),
    mount(ModalCourse, { global, attachTo: attach() }),
    mount(HelpingSlider, { global, attachTo: attach() }),
    mount(LearningModule, { props: moduleProps(), global, attachTo: attach() }),
    mount(CustomNode, {
      props: {
        data: {
          node_id: 'node-1',
          fullname: 'Test Kurs 08',
          course_node_id: [10],
          progress: 0,
          animations: { seenrestriction: true, seencompletion: true },
          completion: { feedback: { status: 'accessible', completion: {} }, restrictioncriteria: {}, singlerestrictionnode: [] },
        },
        learningpath,
        editorview: true,
        zoomstep: 1,
      },
      global,
      attachTo: attach(),
    }),
  ];
};

/** The ids that appear more than once in the document. */
const duplicates = () => {
  const seen = new Map();
  document.querySelectorAll('[id]').forEach((e) => seen.set(e.id, (seen.get(e.id) || 0) + 1));
  return [...seen].filter(([, n]) => n > 1).map(([id]) => id);
};

/**
 * The references in a host that do not name exactly one element of that host.
 *
 * @param {HTMLElement} host The application's root.
 * @returns {string[]}
 */
const broken = (host) => {
  const out = [];
  for (const attr of ['for', 'aria-labelledby', 'aria-describedby', 'aria-controls', 'list', 'data-target']) {
    host.querySelectorAll(`[${attr}]`).forEach((el) => {
      const raw = el.getAttribute(attr);
      const ids = attr === 'data-target' ? [raw.replace(/^#/, '')] : raw.split(/\s+/).filter(Boolean);
      for (const id of ids) {
        const found = document.querySelectorAll(`[id="${id}"]`);
        if (found.length !== 1 || !host.contains(found[0])) {
          out.push(`${attr}=${id}`);
        }
      }
    });
  }
  return out;
};

describe('domIds composable (#18)', () => {
  it('gives every application its own prefix', () => {
    const a = nextAppDomPrefix();
    const b = nextAppDomPrefix();
    expect(a).toMatch(/^adele\d+$/);
    expect(b).not.toBe(a);
  });

  it('prefixes ids and selectors with the store prefix, and leaves them alone without one', () => {
    const store = { state: { domPrefix: 'adele7' } };
    expect(appDomId(store, DIALOGS.node)).toBe('adele7-nodeModal');
    expect(appDomSelector(store, DIALOGS.course)).toBe('#adele7-courseModal');
    expect(appDomId({ state: {} }, 'x')).toBe('x');
    expect(appDomId(null, 'x')).toBe('x');
  });

  it('names VueFlow markers per graph: a marker type becomes an object, anything else stays', () => {
    expect(scopedMarker('arrowclosed')).toEqual({ type: 'arrowclosed' });
    expect(scopedMarker('arrow')).toEqual({ type: 'arrow' });
    expect(scopedMarker('my-marker')).toBe('my-marker');
    expect(scopedMarker(undefined)).toBeUndefined();
    const marker = { type: 'arrowclosed', color: '#000' };
    expect(scopedMarker(marker)).toBe(marker);
    const edges = [{ id: 'e1', markerEnd: 'arrowclosed' }, { id: 'e2' }];
    expect(scopeMarkers(edges)).toBe(edges);
    expect(edges[0].markerEnd).toEqual({ type: 'arrowclosed' });
    expect('markerEnd' in edges[1]).toBe(false);
  });
});

describe('dialogs of two applications on one page (#18)', () => {
  let hosts;
  let wrappers;

  beforeEach(() => {
    hosts = ['adele1', 'adele2'].map((prefix) => {
      const host = document.createElement('div');
      host.setAttribute('name', 'local-adele-app');
      host.dataset.prefix = prefix;
      document.body.appendChild(host);
      return host;
    });
    wrappers = hosts.flatMap((host) => mountApplication(makeStore(host.dataset.prefix), host));
  });

  afterEach(() => {
    wrappers.forEach((w) => w.unmount());
    hosts.forEach((h) => h.remove());
  });

  it('no id is twice in the document', () => {
    expect(duplicates()).toEqual([]);
  });

  it('every label, aria and data-target reference names an element of its own application', () => {
    for (const host of hosts) {
      expect(broken(host)).toEqual([]);
    }
  });

  it.each([
    [DIALOGS.node, 'nodeModal-title'],
    [DIALOGS.course, 'courseModal-title'],
    [DIALOGS.tutorial, 'helpingSlider-title'],
  ])('dialog %s: a dialog, named by its own title, its close button named', (dialog, title) => {
    for (const host of hosts) {
      const prefix = host.dataset.prefix;
      const el = host.querySelector(`[id="${prefix}-${dialog}"]`);
      expect(el).not.toBeNull();
      expect(el.getAttribute('role')).toBe('dialog');
      expect(el.getAttribute('aria-labelledby')).toBe(`${prefix}-${title}`);
      expect(el.querySelector(`[id="${prefix}-${title}"]`)).not.toBeNull();
      expect(el.querySelector('.close').getAttribute('aria-label')).toBe('Close');
    }
  });

  it('the node card opens the node dialog of its own application', () => {
    for (const host of hosts) {
      const prefix = host.dataset.prefix;
      const opener = host.querySelector('[aria-haspopup="dialog"][data-target]');
      expect(opener.getAttribute('data-target')).toBe(`#${prefix}-nodeModal`);
      expect(opener.getAttribute('aria-controls')).toBe(`${prefix}-nodeModal`);
    }
  });

  it('the node and the course dialog no longer share field ids', () => {
    const [host] = hosts;
    const fields = [...host.querySelectorAll('.modal input[id], .modal textarea[id]')].map((e) => e.id);
    expect(fields).toEqual(expect.arrayContaining([
      'adele1-nodeModal-fullname', 'adele1-nodeModal-description', 'adele1-nodeModal-estimate-duration',
      'adele1-courseModal-fullname', 'adele1-courseModal-description',
    ]));
    expect(new Set(fields).size).toBe(fields.length);
  });

  it('the feedback dialog is gone: nothing names it any more (#18 DoD2, removed 2026-11-27)', () => {
    expect(Object.keys(DIALOGS)).toEqual(['node', 'course', 'tutorial']);
    for (const host of hosts) {
      expect(host.querySelector('[id*="feedbackModal"]')).toBeNull();
      expect(host.querySelector('[data-target*="feedbackModal"], [aria-controls*="feedbackModal"]')).toBeNull();
    }
  });
});

describe('the same dialog twice in one application (#18 AK2, DoD3)', () => {
  let host;
  let wrappers;

  beforeEach(() => {
    host = document.createElement('div');
    document.body.appendChild(host);
    const store = makeStore('adele7');
    // Every dialog twice, in one application (one store, one prefix).
    wrappers = mountApplication(store, host).concat(mountApplication(store, host));
  });

  afterEach(() => {
    wrappers.forEach((w) => w.unmount());
    host.remove();
  });

  it('no id is twice; the first instance keeps the id the openers name, the second has its own', () => {
    expect(duplicates()).toEqual([]);
    for (const dialog of Object.values(DIALOGS)) {
      expect(host.querySelectorAll(`[id="adele7-${dialog}"][role="dialog"]`)).toHaveLength(1);
      expect(host.querySelectorAll(`[id="adele7-${dialog}-2"][role="dialog"]`)).toHaveLength(1);
    }
  });

  it('every label and ARIA reference stays inside its own instance', () => {
    expect(broken(host)).toEqual([]);
    for (const dialog of Object.values(DIALOGS)) {
      for (const id of [`adele7-${dialog}`, `adele7-${dialog}-2`]) {
        const el = host.querySelector(`[id="${id}"]`);
        const title = el.getAttribute('aria-labelledby');
        expect(title.startsWith(id + '-')).toBe(true);
        expect(el.querySelector(`[id="${title}"]`)).not.toBeNull();
        el.querySelectorAll('label[for]').forEach((label) => {
          expect(el.querySelector(`[id="${label.getAttribute('for')}"]`)).not.toBeNull();
        });
      }
    }
  });

  it('an instance gives its number back when it unmounts', () => {
    const store = makeStore('adele8');
    const other = document.createElement('div');
    document.body.appendChild(other);
    const first = mountApplication(store, other);
    let second = mountApplication(store, other);
    second.forEach((w) => w.unmount());
    second = mountApplication(store, other);
    expect(other.querySelectorAll('[id="adele8-nodeModal-2"]')).toHaveLength(1);
    expect(other.querySelectorAll('[id="adele8-nodeModal-3"]')).toHaveLength(0);
    first.concat(second).forEach((w) => w.unmount());
    other.remove();
  });
});

describe('the learning-module forms (#18)', () => {
  it('the add form and the edit form, open together, use their own fields and colour lists', async () => {
    const host = document.createElement('div');
    document.body.appendChild(host);
    const wrapper = mount(LearningModule, {
      props: moduleProps(),
      global: { plugins: [makeStore('adele5')], directives: { tooltip: {} } },
      attachTo: host,
    });
    await wrapper.vm.$nextTick();
    await wrapper.find('button').trigger('click');
    await wrapper.find('a[aria-label="Edit: Module 1"]').trigger('click');
    await wrapper.vm.$nextTick();
    const ids = [...host.querySelectorAll('[id]')].map((e) => e.id);
    expect(ids).toEqual(expect.arrayContaining([
      'adele5-module-add-title', 'adele5-module-add-color', 'adele5-module-add-colors',
      'adele5-module-edit-title', 'adele5-module-edit-color', 'adele5-module-edit-colors',
    ]));
    expect(duplicates()).toEqual([]);
    expect(broken(host)).toEqual([]);
    wrapper.unmount();
    host.remove();
  });
});

describe('ScopedEdge (#18)', () => {
  const props = {
    id: 'dndnode_2dndnode_1',
    sourceX: 0,
    sourceY: 0,
    targetX: 100,
    targetY: 100,
    sourcePosition: 'bottom',
    targetPosition: 'top',
    markerEnd: "url('#vue-flow-0__type=arrowclosed')",
  };

  it.each(['bezier', 'smoothstep'])('a %s edge carries the application prefix on its path id only', (shape) => {
    const wrapper = mount(ScopedEdge, { props: { ...props, shape }, global: { plugins: [makeStore('adele3')] } });
    const path = wrapper.find('path.vue-flow__edge-path');
    expect(path.attributes('id')).toBe('adele3-dndnode_2dndnode_1');
    expect(path.attributes('d')).toMatch(/^M/);
    expect(path.attributes('marker-end')).toBe(props.markerEnd);
    wrapper.unmount();
  });
});

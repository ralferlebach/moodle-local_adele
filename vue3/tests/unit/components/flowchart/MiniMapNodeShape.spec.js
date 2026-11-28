/**
 * The minimap node without an id (local_adele #18 AK7, found 2026-11-23):
 * VueFlow's own minimap node gives its rectangle the node's id
 * ("dndnode_1"), which every ADELE application on a page has.
 */
import { mount } from '@vue/test-utils';
import MiniMapNodeShape from '../../../../components/flowchart/MiniMapNodeShape.vue';

const shape = {
  id: 'dndnode_1',
  type: 'custom',
  selected: true,
  dragging: false,
  position: { x: 10, y: 20 },
  dimensions: { width: 400, height: 381 },
  borderRadius: 5,
  color: 'grey',
  shapeRendering: 'geometricPrecision',
  strokeColor: 'transparent',
  strokeWidth: 2,
};

describe('MiniMapNodeShape.vue (#18 AK7)', () => {
  it('draws the node as VueFlow does, but without the node\'s id', () => {
    const wrapper = mount(MiniMapNodeShape, { props: shape });
    const rect = wrapper.find('rect');
    expect(rect.exists()).toBe(true);
    expect(rect.attributes('id')).toBeUndefined();
    expect(rect.classes()).toEqual(expect.arrayContaining(['vue-flow__minimap-node', 'selected']));
    expect(rect.attributes()).toMatchObject({ x: '10', y: '20', width: '400', height: '381', rx: '5', ry: '5',
      fill: 'grey', stroke: 'transparent', 'stroke-width': '2', 'shape-rendering': 'geometricPrecision' });
  });

  it('two applications showing the same path: no id at all, so none twice', () => {
    const a = mount(MiniMapNodeShape, { props: shape });
    const b = mount(MiniMapNodeShape, { props: shape });
    expect([a, b].map((w) => w.find('[id]').exists())).toEqual([false, false]);
  });
});

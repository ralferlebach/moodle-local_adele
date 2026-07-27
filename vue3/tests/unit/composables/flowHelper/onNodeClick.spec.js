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
 * on Node Click.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import onNodeClick from '../../../../composables/flowHelper/onNodeClick';

describe('onNodeClick', () => {
  let event;
  let setCenter;
  let store;

  beforeEach(() => {
    event = {
      node: {
        id: 'test-node',
        position: { x: 100, y: 200 },
        dimensions: { width: 50, height: 50 },
        data: {
          animations: {
            seenrestriction: false,
            seencompletion: false,
          }
        }
      }
    };
    setCenter = jest.fn(() => Promise.resolve());
    store = {
      state: {
        user: 1,
        lpuserpathrelation: { user_id: 1 }
      },
      dispatch: jest.fn()
    };
  });

  it('should should set the center to the given node', async () => {
    await onNodeClick(event, setCenter, store);
    expect(setCenter).toHaveBeenCalledWith(
      125, // 100 + 50/2
      225, // 200 + 50/2
      { zoom: 1, duration: 500 }
    );

    await setCenter();
  });

  it('should trigger the web service', async () => {
    await onNodeClick(event, setCenter, store);

    expect(store.dispatch).toHaveBeenCalledWith('setNodeAnimations', {
      nodeid: 'test-node',
      animations: {
        seenrestriction: true,
        seencompletion: true,
      },
    });
  });

  it('should not trigger the web service if no conditions are met', async () => {
    event.node.data.animations.seenrestriction = true;
    event.node.data.animations.seencompletion = true;
    await onNodeClick(event, setCenter, store);
    expect(store.dispatch).not.toHaveBeenCalled();

    event.node.data.animations = {};
    await onNodeClick(event, setCenter, store);
    expect(store.dispatch).not.toHaveBeenCalled();
  });

  it('should not trigger the web service if no conditions are met', async () => {
    event.node.data = true;
    const result = await onNodeClick(event, setCenter, store);
    expect(store.dispatch).not.toHaveBeenCalled();
    expect(result).toBe(1);
  });
});
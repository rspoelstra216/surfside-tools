from pathlib import Path

path = Path('includes/volunteer-needs.php')
text = path.read_text()

old_actions = '<div class="surfside-volunteer-actions"><button type="button" class="surfside-information-remove" data-volunteer-up>↑</button><button type="button" class="surfside-information-remove" data-volunteer-down>↓</button><button type="button" class="surfside-information-remove" data-volunteer-remove>Remove</button></div>'
new_actions = '<div class="surfside-volunteer-actions"><button type="button" class="surfside-volunteer-control surfside-volunteer-move" data-volunteer-up aria-label="Move volunteer need up" title="Move up">↑</button><button type="button" class="surfside-volunteer-control surfside-volunteer-move" data-volunteer-down aria-label="Move volunteer need down" title="Move down">↓</button><button type="button" class="surfside-volunteer-control" data-volunteer-remove>Remove</button></div>'
if text.count(old_actions) != 2:
    raise SystemExit(f'Expected 2 volunteer action blocks, found {text.count(old_actions)}')
text = text.replace(old_actions, new_actions)

old_add = '<button type="button" class="surfside-information-add" data-volunteer-add>+ Add Volunteer Need</button>'
new_add = '<button type="button" class="surfside-volunteer-add" data-volunteer-add>+ Add Volunteer Need</button>'
if text.count(old_add) != 1:
    raise SystemExit(f'Expected 1 add button, found {text.count(old_add)}')
text = text.replace(old_add, new_add, 1)

old_save = '<div class="surfside-information-actions"><button type="submit" class="surfside-staff-button">Save Volunteer Needs</button></div>'
new_save = '<div class="surfside-volunteer-save-actions"><button type="submit" class="surfside-staff-button">Save Volunteer Needs</button></div>'
if text.count(old_save) != 1:
    raise SystemExit(f'Expected 1 save wrapper, found {text.count(old_save)}')
text = text.replace(old_save, new_save, 1)

old_css = '.surfside-volunteer-actions{display:flex;gap:8px;margin-top:16px}.surfside-mobile-notice'
new_css = '.surfside-volunteer-actions{display:flex;align-items:center;gap:8px;margin-top:16px}.surfside-volunteer-control,.surfside-volunteer-add{min-height:42px;padding:9px 14px;border:1px solid #0b5fa5;border-radius:9px;background:#fff;color:#0b5fa5;font:inherit;font-weight:800;cursor:pointer}.surfside-volunteer-control:hover,.surfside-volunteer-control:focus-visible,.surfside-volunteer-add:hover,.surfside-volunteer-add:focus-visible{background:#eaf3fb;outline:0}.surfside-volunteer-move{min-width:42px;padding-inline:10px}.surfside-volunteer-add{width:100%;min-height:46px}.surfside-volunteer-save-actions{display:flex;justify-content:flex-end}.surfside-volunteer-save-actions .surfside-staff-button{width:100%}.surfside-mobile-notice'
if text.count(old_css) != 1:
    raise SystemExit(f'Expected volunteer action CSS marker once, found {text.count(old_css)}')
text = text.replace(old_css, new_css, 1)

old_mobile = '.surfside-volunteer-need-head{align-items:flex-start}}'
new_mobile = '.surfside-volunteer-need-head{align-items:flex-start}.surfside-volunteer-actions{flex-wrap:wrap}}'
if text.count(old_mobile) != 1:
    raise SystemExit(f'Expected mobile CSS marker once, found {text.count(old_mobile)}')
text = text.replace(old_mobile, new_mobile, 1)

if 'surfside-information-add' in text or 'surfside-information-remove' in text or 'surfside-information-actions' in text:
    raise SystemExit('Volunteer Needs still depends on surfside-information control classes')

path.write_text(text)

-- docs/tools/image-sizes.lua
-- Gives small screenshots a sensible width in the PDF and Word manuals, so the Markdown
-- stays plain (GitHub shows pandoc {width=…} attributes as text).
local sizes = {
  ["kaart"] = "45%", ["kaart-substatus"] = "45%", ["bezoekers-blok"] = "60%",
  ["mobiel"] = "40%", ["profiel"] = "70%", ["bezoeker-registratie"] = "75%",
}
function Image(img)
  local name = img.src:match("([%w%-]+)%.png$")
  if name and sizes[name] then img.attributes.width = sizes[name] end
  return img
end

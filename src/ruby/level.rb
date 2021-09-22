require 'squib'
require 'game_icons'

level = Squib.csv file: 'src/resources/level.csv'
date = DateTime.now.to_s
#print GameIcons.names

Squib::Deck.new(cards: level["id"].size,
                layout: %w(src/resources/Vlayout.yml src/resources/Vcards.yml)) do
                
  deck = level
  rect layout: :bleed
  rect layout: :frame, fill_color: :white

  text str: deck["name"], layout: "Name"

  x = 100
  y = 100
  font_size = 10
  for i in 1..7 do
    id = i.to_i
    id_s = i.to_s
    y_pos = y*(id+1)
    text str: id_s , layout: "Name", font_size: font_size, x: x, y: y_pos
    polygon n: id+2 , layout: "Name", x: x+10, y: y_pos+15, radius: 30
    text layout: "Name", font_size: font_size, x: x+50, y: y_pos, 
        str: deck["count"+id_s].zip(deck["monster"+id_s]).map {|c,m| c.to_s+" "+m.to_s }
  end

  text str: date, layout: :Date
  save_png prefix: deck["name"].map{ |name| "id."+name+"." }, dir: "_levels"

end

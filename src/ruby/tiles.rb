require 'squib'

#deck = Squib.csv file: 'src/resources/land_cards.csv'
deck = [1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1]

def drawCutlines(deck, dirname)  
  ix = inches(1)
  iy = inches(0.866)
  radius = inches(1)
  line x1: 0, y1: 0, x2: 40, y2:0, stroke_color: :red
  line x1: 0, y1: 0, x2: 0, y2:40, stroke_color: :red
  
  for j in 0..5 do
    for i in 0..3 do
      polygon x: ix+(3*i)*ix, y:iy+2*j*iy, n: 6, radius: radius, stroke_color: :red
      polygon x: ix+(3*i+1.5)*ix, y:iy+iy+2*j*iy, n: 6, radius: radius, stroke_color: :red
    end
  end
  save_png prefix: deck.map{|str| str.to_s+"."}, dir: dirname
end

Squib::Deck.new(cards: 1,
#                layout: 'src/resources/tiles-layout.yml',
                width: "210mm", height: "210mm") do # height = width*sqrt(3)/2

  drawCutlines(deck, '_tiles')
end
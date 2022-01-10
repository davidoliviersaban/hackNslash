require 'squib'

#deck = Squib.csv file: 'src/resources/land_cards.csv'
deck = ['floor1.png', 'floor2.png', 'floor3.png']
floor_size = '202mm'
date = DateTime.now.to_s

def hexGrid()
  size = 2
  [ inches(size), inches(size * 0.866), inches(size * 0.5) ]
end

def squareGrid()
  size = 1.5
  [ inches(size), inches(size), inches(size * 0.5) ]
end 

def drawHexagonalGrid(deck, dirname, floor_size, date) 
  dimensions = hexGrid()
  ix = dimensions[0]
  iy = dimensions[1]
  radius = dimensions[2]
  stroke_width= 10

  png file: deck.map{|str| 'src/resources/images/'+str}, width: floor_size, height: floor_size

  line x1: 0, y1: 0, x2: 40, y2:0, stroke_color: :red
  line x1: 0, y1: 0, x2: 0, y2:40, stroke_color: :red
  
  for j in 0..10 do
    for i in 0..5 do
      polygon x: (i*3)*radius-radius/2, y:j*iy, n: 6, radius: radius, stroke_color: :red, stroke_width: stroke_width
      polygon x: (3*i+1.5)*radius-radius/2, y:j*iy+iy/2, n: 6, radius: radius, stroke_color: :red, stroke_width: stroke_width
    end
  end

  text str: date, x: 10, y: 10
  save_png prefix: deck.map{|str| 'hex.'+str}, dir: dirname, font_size: 6
end

def drawSquareGrid(deck, dirname, floor_size, date)  
  dimensions = squareGrid()
  ix = dimensions[0]
  iy = dimensions[1]
  radius = dimensions[2]
  stroke_width= 10

  png file: deck.map{|str| 'src/resources/images/'+str}, width: floor_size, height: floor_size

  line x1: 0, y1: 0, x2: 40, y2:0, stroke_color: :red
  line x1: 0, y1: 0, x2: 0, y2:40, stroke_color: :red
  
  for j in 0..10 do
    for i in 0..10 do
      # With no Angle
      # polygon x: i*ix+ix/2, y:j*iy+iy/2, n: 4, radius: radius, stroke_color: :red, stroke_width: stroke_width
      rect x: i*ix+20, y:j*iy+20, width: radius*2, height: radius*2, stroke_color: :red, stroke_width: stroke_width
    end
  end

  text str: date, x: 10, y: 10
  save_png prefix: deck.map{|str| 'square.'+str}, dir: dirname, font_size: 6
end

Squib::Deck.new(cards: deck.size,
#                layout: 'src/resources/tiles-layout.yml',
                width: floor_size, height: floor_size) do # height = width*sqrt(3)/2

    drawHexagonalGrid(deck, '_tiles', floor_size, date)
end


Squib::Deck.new(cards: deck.size,
#                layout: 'src/resources/tiles-layout.yml',
                width: floor_size, height: floor_size) do # height = width*sqrt(3)/2

    drawSquareGrid(deck, '_tiles', floor_size, date)
end
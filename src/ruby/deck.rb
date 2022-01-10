require 'squib'
require 'game_icons'

bonus = Squib.csv file: 'src/resources/bonus-fr.csv'
bestiary = Squib.csv file: 'src/resources/bestiary.csv'
bosses = Squib.csv file: 'src/resources/bosses.csv'
date = DateTime.now.to_s
#print GameIcons.names

def transparent()
  '#00000000'
end

def rankIcon(rank)
  if (rank == 1)
    GameIcons.get('rank-1').file
  elsif (rank == 2)
    GameIcons.get('rank-2').file
  elsif (rank == 3)
    GameIcons.get('rank-3').file
  else
    'src/resources/images/rank-0.svg'
  end
end

def getIcon(name)
  if (name == 'Targets')
    GameIcons.get('human-target').file
  elsif (name == 'Damage')
    GameIcons.get('thunder-blade').file
  elsif (name == 'Dash')
    GameIcons.get('wingfoot').file
  elsif (name == 'Range')  
    GameIcons.get('high-shot').file
  elsif (name == 'PushDistance')
    GameIcons.get('push').file
  elsif (name == 'PullDistance')
    GameIcons.get('pull').file
  elsif (name == 'DrawCards')
    GameIcons.get('card-draw').file
  elsif (name == 'Combo')
    GameIcons.get('extra-time').file
  elsif (name == 'Health')
    GameIcons.get('life-bar').file
  elsif (name == 'Size')
    GameIcons.get('growth').file
  end
end

Squib::Deck.new(cards: bonus['Name'].size,
                layout: %w(src/resources/Vlayout.yml src/resources/Vcards.yml)) do
                
  deck = bonus
  background color: 'white'
  rect layout: :bleed
  rect layout: :frame
  
  text str: deck['Name'], layout: 'Name'
  %w(Targets Damage Dash Range PushDistance PullDistance DrawCards Combo).each do |key|
    text str: deck[key], layout: key, color: deck[key].map{ |value|
      if (value != 0 || key == 'Range')
        '#000000ff'
      else
        transparent()
      end
    }
    svg layout: key+'Icon', file: getIcon(key), mask: deck[key].map{ |value|
      if (value != 0 || key == 'Range')
        ''
      else
        transparent()
      end
    }
  end

  svg layout: 'Rank', file: deck['Rank'].map{ |rank| rankIcon(rank) }

  # svg layout: 'TargetsIcon', file: GameIcons.get('human-target').file
  # svg layout: 'DamageIcon', file: GameIcons.get('thunder-blade').file
  # svg layout: 'DashIcon', file: GameIcons.get('wingfoot').file
  # svg layout: 'RangeIcon', file: GameIcons.get('high-shot').file
  # svg layout: 'PushDistanceIcon', file: GameIcons.get('push').file
  # svg layout: 'PullDistanceIcon', file: GameIcons.get('pull').file
  # svg layout: 'DrawCardsIcon', file: GameIcons.get('card-draw').file
  # svg layout: 'ComboIcon', file: GameIcons.get('extra-time').file

  text str: date, layout: :Date
  save_png prefix: deck['Name'].zip(deck['Rank']).map{ |name,rank| 'bonus.'+rank.to_s+'.'+name }

end

#print GameIcons.names

Squib::Deck.new(cards: bestiary['Name'].size,
                layout: %w(src/resources/Vlayout.yml src/resources/Vcards.yml)) do

  deck = bestiary
  background color: 'orange'
  rect layout: :bleed
  rect layout: :frame

  text layout: 'Name', str: deck['Name'].zip(deck['Level']).map{ |name,level| name+' '+level.to_s}
  text str: deck['Description'], layout: 'Description'

  %w(Size Dash Attack Health Damage Range).each do |key|
    text str: deck[key], layout: key
    svg layout: key+'Icon', file: getIcon(key)
  end

  svg layout: 'Rank', file: deck['Level'].map{ |rank| rankIcon(rank) }

  # svg layout: 'AttackIcon', file: GameIcons.get('evil-hand').file
  # svg layout: 'DamageIcon', file: GameIcons.get('thunder-blade').file
  # svg layout: 'DashIcon', file: GameIcons.get('wingfoot').file
  # svg layout: 'RangeIcon', file: GameIcons.get('high-shot').file
  # svg layout: 'PushDistanceIcon', file: GameIcons.get('push').file
  # svg layout: 'PullDistanceIcon', file: GameIcons.get('pull').file
  # svg layout: 'HealthIcon', file: GameIcons.get('life-bar').file
  # svg layout: 'SizeIcon', file: GameIcons.get('growth').file

  text str: date, layout: :Date
  save_png prefix: deck['Name'].zip(deck['Level']).map{ |name,level| 'bestiary.'+level.to_s+'.'+name }

end


#print GameIcons.names

Squib::Deck.new(cards: bosses['Name'].size,
                layout: %w(src/resources/Vlayout.yml src/resources/Vcards.yml)) do

  deck = bosses
  background color: 'darkred'
  rect layout: :bleed
  rect layout: :frame
  text layout: 'Name', color: 'white', str: deck['Name'].zip(deck['Level']).map{ |name,level| name+' '+level.to_s}
  text str: deck['Description'], layout: 'Description', color: 'white'

  %w(Size Dash Attack Health Damage Range).each do |key|
    text str: deck[key], layout: key, color: 'white'
    svg layout: key+'Icon', file: getIcon(key)
  end

  svg layout: 'Rank', file: deck['Level'].map{ |rank| rankIcon(rank) }

  # svg layout: 'AttackIcon', file: GameIcons.get('evil-hand').file
  # svg layout: 'DamageIcon', file: GameIcons.get('thunder-blade').file
  # svg layout: 'DashIcon', file: GameIcons.get('wingfoot').file
  # svg layout: 'RangeIcon', file: GameIcons.get('high-shot').file
  # svg layout: 'PushDistanceIcon', file: GameIcons.get('push').file
  # svg layout: 'PullDistanceIcon', file: GameIcons.get('pull').file
  # svg layout: 'HealthIcon', file: GameIcons.get('life-bar').file
  # svg layout: 'SizeIcon', file: GameIcons.get('growth').file

  text str: date, layout: :Date
  save_png prefix: deck['Name'].zip(deck['Level']).map{ |name,level| 'bosses.'+level.to_s+'.'+name }

end
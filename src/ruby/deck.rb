require 'squib'
require 'game_icons'

bonus = Squib.csv file: 'src/resources/bonus-fr.csv'
bestiary = Squib.csv file: 'src/resources/bestiary.csv'
bosses = Squib.csv file: 'src/resources/bosses.csv'
date = DateTime.now.to_s
#print GameIcons.names

def monster_card_background()
  '#f3ead8'
end

def monster_card_line()
  '#9e9076'
end

def monster_card_text()
  '#151515'
end

def normalize_text(value)
  value.to_s.strip
end

def parse_keywords(size, dash)
  keywords = []
  keywords << normalize_text(size).capitalize unless normalize_text(size).empty?
  dash_text = normalize_text(dash)
  keywords << "Move #{dash_text}" unless dash_text.empty? || dash_text == '0'
  keywords
end

def special_ability_text(attack, description)
  attack_text = normalize_text(attack)
  description_text = normalize_text(description)
  return description_text if attack_text.empty?
  return attack_text if description_text.empty?

  "#{attack_text} - #{description_text}"
end

def ai_logic_text(dash, range, damage)
  move_text = normalize_text(dash)
  range_text = normalize_text(range)
  damage_text = normalize_text(damage)
  move_step = move_text == '0' ? 'hold' : "move #{move_text}"
  range_step = range_text == '0' ? 'melee' : "range #{range_text}"
  damage_step = damage_text.empty? ? 'attack' : "attack #{damage_text}"

  "nearest hero -> #{move_step} -> #{range_step} -> #{damage_step}"
end

def placeholder_portrait_label(name)
  normalize_text(name).upcase
end

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
    'src/resources/images/cards/ranks/rank-0.svg'
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
  elsif (name == 'Nearest')
    GameIcons.get('human-target').file
  elsif (name == 'Move')
    GameIcons.get('wingfoot').file
  elsif (name == 'Melee')
    GameIcons.get('broadsword').file
  elsif (name == 'Ranged')
    GameIcons.get('high-shot').file
  elsif (name == 'Small')
    GameIcons.get('mouse').file
  elsif (name == 'Large')
    GameIcons.get('ogre').file
  elsif (name == 'Push')
    GameIcons.get('push').file
  elsif (name == 'Prison')
    GameIcons.get('imprisoned').file
  elsif (name == 'Fly')
    GameIcons.get('batwings').file
  elsif (name == 'Spawn')
    GameIcons.get('spawn-node').file
  elsif (name == 'Explosion')
    GameIcons.get('explosion-rays').file
  elsif (name == 'Magic')
    GameIcons.get('magic-swirl').file
  elsif (name == 'Whirl')
    GameIcons.get('whirlwind').file
  elsif (name == 'Shield')
    GameIcons.get('shield').file
  elsif (name == 'Trap')
    GameIcons.get('spiky-pit').file
  elsif (name == 'Act')
    GameIcons.get('stopwatch').file
  end
end

def keyword_icon_name(size, dash)
  keywords = []
  keywords << (normalize_text(size) == 'petit' ? 'Small' : 'Large') unless normalize_text(size).empty?
  dash_text = normalize_text(dash)
  keywords << 'Move' unless dash_text.empty? || dash_text == '0'
  keywords << 'Fly' if dash_text.include?('vol')
  keywords
end

def keyword_label_text(size, dash)
  labels = []
  labels << normalize_text(size).capitalize unless normalize_text(size).empty?
  dash_text = normalize_text(dash)
  labels << "Move #{dash_text.gsub(' vol', '')}" unless dash_text.empty? || dash_text == '0'
  labels << 'Fly' if dash_text.include?('vol')
  labels.first(3)
end

def special_icon_name(attack, description)
  text = "#{normalize_text(attack)} #{normalize_text(description)}".downcase
  return 'Prison' if text.include?('prison')
  return 'Push' if text.include?('repousse')
  return 'Explosion' if text.include?('explosion')
  return 'Spawn' if text.include?('spawn') || text.include?('gobelin') || text.include?('generateur')
  return 'Shield' if text.include?('bouclier') || text.include?('shield')
  return 'Whirl' if text.include?('tourbillon') || text.include?('spin')
  return 'Magic' if text.include?('sort') || text.include?('rayon') || text.include?('faiblesse') || text.include?('douleur')

  'Damage'
end

def special_label_text(attack, description)
  attack_text = normalize_text(attack)
  description_text = normalize_text(description)
  return attack_text unless attack_text.empty?
  return description_text unless description_text.empty?

  'Ability'
end

def ai_icon_names(range)
  range_text = normalize_text(range)
  attack_icon = range_text == '0' ? 'Melee' : 'Ranged'
  ['Nearest', 'Move', attack_icon]
end

def ai_labels(dash, range, damage)
  move_text = normalize_text(dash)
  range_text = normalize_text(range)
  damage_text = normalize_text(damage)
  move_label = move_text == '0' ? 'Hold' : "Move #{move_text.gsub(' vol', '')}"
  range_label = range_text == '0' ? 'Melee' : "Range #{range_text}"
  attack_label = damage_text.empty? ? 'Atk' : "Atk #{damage_text}"
  ['Nearest', move_label, range_label == 'Melee' ? attack_label : "#{range_label} #{attack_label}"]
end

def heart_positions(health, start_x, start_y, columns, step_x, step_y)
  count = normalize_text(health).to_i
  Array.new(count) do |index|
    col = index % columns
    row = index / columns
    [start_x + (col * step_x), start_y + (row * step_y)]
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
                width: '63mm', height: '44mm', layout: %w(src/resources/Vlayout.yml src/resources/monster_cards.yml)) do

  deck = bestiary
  background color: monster_card_background()
  rect layout: :monster_bleed, fill_color: monster_card_background(), stroke_color: monster_card_background()
  rect layout: :monster_frame, fill_color: monster_card_background(), stroke_color: monster_card_line(), stroke_width: 4
  rect layout: :MonsterPortrait, fill_color: '#f7f1e4', stroke_color: '#5b5145', stroke_width: 4
  line x1: 350, y1: 125, x2: 815, y2: 125, stroke_color: monster_card_line(), stroke_width: 3
  line x1: 350, y1: 290, x2: 815, y2: 290, stroke_color: monster_card_line(), stroke_width: 3
  line x1: 350, y1: 445, x2: 815, y2: 445, stroke_color: monster_card_line(), stroke_width: 3
  ellipse x: 707, y: 132, width: 100, height: 100, fill_color: '#cf423b', stroke_color: '#7d1713', stroke_width: 4
  rect x: 704, y: 215, width: 38, height: 38, fill_color: '#efe3c9', stroke_color: '#c5b89f', stroke_width: 2

  text layout: 'MonsterPortrait', str: deck['Name'].map { |name| placeholder_portrait_label(name) }, color: '#7a7268', font: 'ChunkFive Roman,Bold 13', valign: 'middle', align: 'center'
  text layout: 'MonsterName', str: deck['Name'].zip(deck['Level']).map { |name, level| "#{name} #{level}" }, color: monster_card_text()
  text layout: 'MonsterSize', str: deck['Size'].map { |size| normalize_text(size).upcase }, color: monster_card_text()
  text layout: 'MonsterAiTitle', str: ['AI LOGIC'] * deck['Name'].size, color: monster_card_text()
  svg layout: 'MonsterAiIcon1', file: deck['Range'].map { |range| getIcon(ai_icon_names(range)[0]) }
  svg layout: 'MonsterAiIcon2', file: deck['Range'].map { |range| getIcon(ai_icon_names(range)[1]) }
  svg layout: 'MonsterAiIcon3', file: deck['Range'].map { |range| getIcon(ai_icon_names(range)[2]) }
  text layout: 'MonsterAiArrow1', str: ['>'] * deck['Name'].size, color: monster_card_text()
  text layout: 'MonsterAiArrow2', str: ['>'] * deck['Name'].size, color: monster_card_text()
  text layout: 'MonsterAiLabel1', str: deck['Dash'].zip(deck['Range'], deck['Damage']).map { |dash, range, damage| ai_labels(dash, range, damage)[0] }, color: monster_card_text()
  text layout: 'MonsterAiLabel2', str: deck['Dash'].zip(deck['Range'], deck['Damage']).map { |dash, range, damage| ai_labels(dash, range, damage)[1] }, color: monster_card_text()
  text layout: 'MonsterAiLabel3', str: deck['Dash'].zip(deck['Range'], deck['Damage']).map { |dash, range, damage| ai_labels(dash, range, damage)[2] }, color: monster_card_text()
  text layout: 'MonsterKeywordTitle', str: ['KEYWORDS'] * deck['Name'].size, color: monster_card_text()
  svg layout: 'MonsterKeywordIcon1', file: deck['Size'].zip(deck['Dash']).map { |size, dash| getIcon(keyword_icon_name(size, dash)[0] || 'Small') }
  svg layout: 'MonsterKeywordIcon2', file: deck['Size'].zip(deck['Dash']).map { |size, dash| getIcon(keyword_icon_name(size, dash)[1] || 'Move') }
  svg layout: 'MonsterKeywordIcon3', file: deck['Attack'].zip(deck['Description']).map { |attack, description| getIcon(special_icon_name(attack, description)) }
  text layout: 'MonsterKeywordLabel1', str: deck['Size'].zip(deck['Dash']).map { |size, dash| keyword_label_text(size, dash)[0] || '' }, color: monster_card_text()
  text layout: 'MonsterKeywordLabel2', str: deck['Size'].zip(deck['Dash']).map { |size, dash| keyword_label_text(size, dash)[1] || '' }, color: monster_card_text()
  text layout: 'MonsterKeywordLabel3', str: deck['Size'].zip(deck['Dash']).map { |size, dash| keyword_label_text(size, dash)[2] || special_label_text('', '') }, color: monster_card_text()
  text layout: 'MonsterSpecialTitle', str: ['SPECIAL'] * deck['Name'].size, color: monster_card_text()
  svg layout: 'MonsterSpecialIcon', file: deck['Attack'].zip(deck['Description']).map { |attack, description| getIcon(special_icon_name(attack, description)) }
  text layout: 'MonsterSpecialLabel', str: deck['Attack'].zip(deck['Description']).map { |attack, description| special_label_text(attack, description) }, color: monster_card_text()
  text layout: 'MonsterActivationText', str: ['ACT'] * deck['Name'].size, color: monster_card_text()
  svg layout: 'MonsterActivationIcon', file: [getIcon('Act')] * deck['Name'].size
  deck['Health'].each_with_index do |health, card_index|
    heart_positions(health, 594, 94, 3, 20, 20).each do |x, y|
      svg file: GameIcons.get('heart-organ').file, x: x, y: y, width: 18, height: 18, range: card_index, mask: '#ffffff'
    end
  end
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

  text str: date, layout: :Date
  save_png prefix: deck['Name'].zip(deck['Level']).map{ |name,level| 'bosses.'+level.to_s+'.'+name }

end

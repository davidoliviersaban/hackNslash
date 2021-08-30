require 'squib'
require 'game_icons'

bonus = Squib.csv file: 'src/resources/bonus.csv'
bestiary = Squib.csv file: 'src/resources/bestiary.csv'
bosses = Squib.csv file: 'src/resources/bosses.csv'
date = DateTime.now.to_s
#print GameIcons.names

Squib::Deck.new(cards: bonus["Name"].size,
                layout: %w(src/resources/Vlayout.yml src/resources/Vcards.yml)) do
                
  deck = bonus
  rect layout: :frame
  rect layout: :bleed
  
  %w(Name Targets Damage Dash Range PushDistance PullDistance DrawCards Combo).each do |key|
    text str: deck[key], layout: key
  end

  svg layout: "Rank", file: deck["Rank"].map{ |rank| 
    if (rank == 1)
      GameIcons.get('rank-1').file
    elsif (rank == 2)
      GameIcons.get('rank-2').file
    elsif (rank == 3)
      GameIcons.get('rank-3').file
    else
      'src/resources/images/rank-0.svg'
    end
    }

  svg layout: "TargetsIcon", file: GameIcons.get('human-target').file
  svg layout: "DamageIcon", file: GameIcons.get('thunder-blade').file
  svg layout: "DashIcon", file: GameIcons.get('wingfoot').file
  svg layout: "RangeIcon", file: GameIcons.get('high-shot').file
  svg layout: "PushDistanceIcon", file: GameIcons.get('push').file
  svg layout: "PullDistanceIcon", file: GameIcons.get('pull').file
  svg layout: "DrawCardsIcon", file: GameIcons.get('card-draw').file
  svg layout: "ComboIcon", file: GameIcons.get('extra-time').file

  text str: date, layout: :Date
  save_png prefix: deck["Name"].map{ |name| "bonus."+name }

end

#print GameIcons.names

Squib::Deck.new(cards: bestiary["Name"].size,
                layout: %w(src/resources/Vlayout.yml src/resources/Vcards.yml)) do

  deck = bestiary
  rect layout: :frame
  rect layout: :bleed

  text layout: "Name", str: deck["Name"].zip(deck["Level"]).map{ |name,level| name+" "+level.to_s}

  %w(Description Size Dash Attack Health Damage Range).each do |key|
    text str: deck[key], layout: key
  end

  svg layout: "AttackIcon", file: GameIcons.get('evil-hand').file
  svg layout: "DamageIcon", file: GameIcons.get('thunder-blade').file
  svg layout: "DashIcon", file: GameIcons.get('wingfoot').file
  svg layout: "RangeIcon", file: GameIcons.get('high-shot').file
  svg layout: "PushDistanceIcon", file: GameIcons.get('push').file
  svg layout: "PullDistanceIcon", file: GameIcons.get('pull').file
  svg layout: "HealthIcon", file: GameIcons.get('life-bar').file
  svg layout: "SizeIcon", file: GameIcons.get('growth').file

  text str: date, layout: :Date
  save_png prefix: deck["Name"].zip(deck["Level"]).map{ |name,level| "bestiary."+level.to_s+"."+name }

end


#print GameIcons.names

Squib::Deck.new(cards: bosses["Name"].size,
                layout: %w(src/resources/Vlayout.yml src/resources/Vcards.yml)) do

  deck = bosses
  background color: 'darkred'
  rect layout: :frame
  rect layout: :bleed
  text layout: "Name", color: 'white', str: deck["Name"].zip(deck["Level"]).map{ |name,level| name+" "+level.to_s}

  %w(Description Size Dash Attack Health Damage Range).each do |key|
    text str: deck[key], layout: key, color: 'white'
  end

  svg layout: "AttackIcon", file: GameIcons.get('evil-hand').file
  svg layout: "DamageIcon", file: GameIcons.get('thunder-blade').file
  svg layout: "DashIcon", file: GameIcons.get('wingfoot').file
  svg layout: "RangeIcon", file: GameIcons.get('high-shot').file
  svg layout: "PushDistanceIcon", file: GameIcons.get('push').file
  svg layout: "PullDistanceIcon", file: GameIcons.get('pull').file
  svg layout: "HealthIcon", file: GameIcons.get('life-bar').file
  svg layout: "SizeIcon", file: GameIcons.get('growth').file

  text str: date, layout: :Date
  save_png prefix: deck["Name"].zip(deck["Level"]).map{ |name,level| "bosses."+level.to_s+"."+name }

end
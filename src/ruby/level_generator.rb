require 'fileutils'
require 'json'

class LevelGenerator
  Cell = Struct.new(:x, :y)

  TERRAIN_FLOOR = 'floor'.freeze
  TERRAIN_WALL = 'wall'.freeze
  TERRAIN_PILLAR = 'pillar'.freeze
  TERRAIN_SPIKES = 'spikes'.freeze
  TERRAIN_HOLE = 'hole'.freeze
  TERRAIN_ENTRY = 'entry'.freeze
  TERRAIN_EXIT = 'exit'.freeze

  COUNTS_BY_SIZE = {
    5 => { walls: 2, pillars: 1, spikes: 2, holes: 1, monsters: 7 },
    7 => { walls: 6, pillars: 4, spikes: 4, holes: 3, monsters: 7 }
  }.freeze

  TERRAIN_TO_CHAR = {
    TERRAIN_FLOOR => '.',
    TERRAIN_WALL => '#',
    TERRAIN_PILLAR => 'P',
    TERRAIN_SPIKES => '^',
    TERRAIN_HOLE => 'X',
    TERRAIN_ENTRY => 'E',
    TERRAIN_EXIT => 'S'
  }.freeze

  MONSTER_LABELS = %w[1 2 3 4 5 6 7].freeze
  PLAYER_LABELS = %w[A B].freeze
  TILE_COLORS = {
    TERRAIN_FLOOR => '#f2efe6',
    TERRAIN_WALL => '#2f2f39',
    TERRAIN_PILLAR => '#2f2f39',
    TERRAIN_SPIKES => '#d97706',
    TERRAIN_HOLE => '#d97706',
    TERRAIN_ENTRY => '#2563eb',
    TERRAIN_EXIT => '#16a34a'
  }.freeze

  def initialize(size:, seed: Random.new_seed)
    raise ArgumentError, 'size must be 5 or 7' unless COUNTS_BY_SIZE.key?(size)

    @playable_size = size
    @seed = seed
    @rng = Random.new(seed)
    @counts = COUNTS_BY_SIZE.fetch(size)
  end

  def generate
    100.times do
      level = generate_once
      return level if level
    end

    raise 'could not generate a valid level after 100 attempts'
  end

  private

  attr_reader :playable_size, :rng, :counts, :seed

  def generate_once
    grid = Array.new(grid_size) { Array.new(grid_size, TERRAIN_FLOOR) }
    fill_border_with_walls(grid)
    entry, exit = choose_entry_and_exit
    grid[entry.y][entry.x] = TERRAIN_ENTRY
    grid[exit.y][exit.x] = TERRAIN_EXIT

    entrance_anchor = entrance_anchor_for(entry)
    player_starts = choose_player_starts(entry)
    reserved = [entry, exit, entrance_anchor, *player_starts]

    place_blocking_terrain(grid, TERRAIN_WALL, counts[:walls], reserved)
    place_blocking_terrain(grid, TERRAIN_PILLAR, counts[:pillars], reserved)
    place_blocking_terrain(grid, TERRAIN_HOLE, counts[:holes], reserved)
    place_spikes(grid, counts[:spikes], reserved)

    reachable_floor_cells = reachable_cells(grid, entry).select do |cell|
      terrain = grid[cell.y][cell.x]
      [TERRAIN_FLOOR, TERRAIN_ENTRY, TERRAIN_EXIT].include?(terrain)
    end

    monster_candidates = reachable_floor_cells.reject do |cell|
      reserved.any? { |reserved_cell| same_cell?(cell, reserved_cell) }
    end

    return nil if monster_candidates.size < counts[:monsters]

    monster_cells = monster_candidates.shuffle(random: rng).first(counts[:monsters])
    monsters = monster_cells.each_with_index.map do |cell, index|
      { label: MONSTER_LABELS.fetch(index), x: cell.x, y: cell.y }
    end

    {
      size: playable_size,
      grid_size: grid_size,
      seed: seed,
      terrain: terrain_payload(grid),
      player_starts: player_starts.map { |cell| cell_payload(cell) },
      monster_starts: monsters,
      entry: cell_payload(entry),
      exit: cell_payload(exit),
      ascii: ascii_map(grid, player_starts, monsters)
    }
  rescue RuntimeError
    nil
  end

  public

  def export_svg(level, output_dir: 'generated_levels')
    FileUtils.mkdir_p(output_dir)

    file_path = File.join(output_dir, "level-#{level[:size]}x#{level[:size]}-#{level[:seed]}.svg")
    File.write(file_path, svg_document(level))
    file_path
  end

  private

  def fill_border_with_walls(grid)
    border_cells.each do |cell|
      grid[cell.y][cell.x] = TERRAIN_WALL
    end
  end

  def choose_entry_and_exit
    entry = entry_cells.sample(random: rng)
    exit_candidates = opposite_border_cells_for(entry)
    exit = exit_candidates.sample(random: rng)

    raise 'not enough room for entry and exit' unless exit

    [entry, exit]
  end

  def opposite_border_cells_for(entry)
    if entry.y.zero?
      border_cells.select { |cell| cell.y == grid_size - 1 }
    else
      border_cells.select { |cell| cell.y.zero? }
    end
  end

  def choose_player_starts(edge)
    anchor = entrance_anchor_for(edge)
    player_one = Cell.new(anchor.x - 1, anchor.y)
    player_two = Cell.new(anchor.x + 1, anchor.y)

    raise 'player starts must stay inside the room' unless [player_one, player_two].all? { |cell| inside_playable_area?(cell) }

    [player_one, player_two]
  end

  def entrance_anchor_for(entry)
    if entry.y.zero?
      Cell.new(entry.x, 1)
    else
      Cell.new(entry.x, grid_size - 2)
    end
  end

  def place_blocking_terrain(grid, terrain, count, reserved)
    count.times do
      placed = false

      shuffled_candidate_cells(reserved).each do |cell|
        next unless grid[cell.y][cell.x] == TERRAIN_FLOOR
        next if terrain == TERRAIN_WALL && !adjacent_to_wall?(grid, cell)

        grid[cell.y][cell.x] = terrain

        if valid_layout?(grid, reserved)
          placed = true
          break
        end

        grid[cell.y][cell.x] = TERRAIN_FLOOR
      end

      raise "could not place #{terrain}" unless placed
    end
  end

  def place_spikes(grid, count, reserved)
    candidates = shuffled_candidate_cells(reserved).select { |cell| grid[cell.y][cell.x] == TERRAIN_FLOOR }
    spikes = candidates.first(count)

    raise 'could not place spikes' if spikes.size < count

    spikes.each do |cell|
      grid[cell.y][cell.x] = TERRAIN_SPIKES
    end
  end

  def valid_layout?(grid, reserved)
    start = reserved.first
    reachable = reachable_cells(grid, start)

    reserved.all? { |cell| reachable.any? { |candidate| same_cell?(candidate, cell) } } &&
      reachable.count { |cell| [TERRAIN_FLOOR, TERRAIN_ENTRY, TERRAIN_EXIT, TERRAIN_SPIKES].include?(grid[cell.y][cell.x]) } >= counts[:monsters] + reserved.size
  end

  def reachable_cells(grid, start)
    queue = [start]
    visited = { key_for(start) => true }
    output = []

    until queue.empty?
      cell = queue.shift
      output << cell

      neighbors_for(cell).each do |neighbor|
        next if visited[key_for(neighbor)]
        next unless walkable?(grid, neighbor)

        visited[key_for(neighbor)] = true
        queue << neighbor
      end
    end

    output
  end

  def walkable?(grid, cell)
    [TERRAIN_FLOOR, TERRAIN_SPIKES, TERRAIN_ENTRY, TERRAIN_EXIT].include?(grid[cell.y][cell.x])
  end

  def adjacent_to_wall?(grid, cell)
    neighbors_for(cell).any? { |neighbor| grid[neighbor.y][neighbor.x] == TERRAIN_WALL }
  end

  def neighbors_for(cell)
    [[1, 0], [-1, 0], [0, 1], [0, -1]].each_with_object([]) do |(dx, dy), neighbors|
      x = cell.x + dx
      y = cell.y + dy
      next if x.negative? || y.negative? || x >= grid_size || y >= grid_size

      neighbors << Cell.new(x, y)
    end
  end

  def border_cells
    @border_cells ||= all_cells.select do |cell|
      cell.x.zero? || cell.y.zero? || cell.x == grid_size - 1 || cell.y == grid_size - 1
    end
  end

  def side_border_cells
    @side_border_cells ||= border_cells.reject do |cell|
      (cell.x.zero? || cell.x == grid_size - 1) && (cell.y.zero? || cell.y == grid_size - 1)
    end
  end

  def entry_cells
    @entry_cells ||= border_cells.select do |cell|
      next false unless (cell.y.zero? || cell.y == grid_size - 1)

      cell.x >= 2 && cell.x <= grid_size - 3
    end
  end

  def inner_cells
    @inner_cells ||= all_cells.select do |cell|
      cell.x.positive? && cell.y.positive? && cell.x < grid_size - 1 && cell.y < grid_size - 1
    end
  end

  def all_cells
    @all_cells ||= Array.new(grid_size * grid_size) do |index|
      Cell.new(index % grid_size, index / grid_size)
    end
  end

  def grid_size
    playable_size + 2
  end

  def inside_playable_area?(cell)
    cell.x.positive? && cell.y.positive? && cell.x < grid_size - 1 && cell.y < grid_size - 1
  end

  def shuffled_candidate_cells(reserved)
    inner_cells.reject do |cell|
      reserved.any? { |reserved_cell| same_cell?(cell, reserved_cell) }
    end.shuffle(random: rng)
  end

  def terrain_payload(grid)
    grid.each_with_index.flat_map do |row, y|
      row.each_with_index.map do |terrain, x|
        { x: x, y: y, terrain: terrain }
      end
    end
  end

  def ascii_map(grid, player_starts, monsters)
    monster_lookup = monsters.each_with_object({}) do |monster, hash|
      hash["#{monster[:x]},#{monster[:y]}"] = monster[:label]
    end
    player_lookup = {
      key_for(player_starts[0]) => PLAYER_LABELS[0],
      key_for(player_starts[1]) => PLAYER_LABELS[1]
    }

    grid.each_with_index.map do |row, y|
      row.each_with_index.map do |terrain, x|
        key = "#{x},#{y}"
        player_lookup[key] || monster_lookup[key] || TERRAIN_TO_CHAR.fetch(terrain)
      end.join(' ')
    end.join("\n")
  end

  def svg_document(level)
    cell_size = 72
    padding = 24
    title_height = 48
    width = level[:grid_size] * cell_size + (padding * 2)
    height = level[:grid_size] * cell_size + (padding * 2) + title_height

    players = level[:player_starts].each_with_index.each_with_object({}) do |(cell, index), lookup|
      lookup["#{cell[:x]},#{cell[:y]}"] = PLAYER_LABELS.fetch(index)
    end
    monsters = level[:monster_starts].each_with_object({}) do |monster, lookup|
      lookup["#{monster[:x]},#{monster[:y]}"] = monster[:label]
    end

    cells = level[:terrain].map do |cell|
      x = padding + cell[:x] * cell_size
      y = padding + title_height + cell[:y] * cell_size
      fill = TILE_COLORS.fetch(cell[:terrain])
      label = players["#{cell[:x]},#{cell[:y]}"] || monsters["#{cell[:x]},#{cell[:y]}"] || TERRAIN_TO_CHAR.fetch(cell[:terrain], '')
      text_color = %w[wall pillar].include?(cell[:terrain]) ? '#f9fafb' : '#111827'

      [
        %(<rect x="#{x}" y="#{y}" width="#{cell_size}" height="#{cell_size}" rx="10" fill="#{fill}" stroke="#1f2937" stroke-width="2" />),
        %(<text x="#{x + (cell_size / 2)}" y="#{y + (cell_size / 2) + 9}" text-anchor="middle" font-family="Verdana, sans-serif" font-size="26" font-weight="700" fill="#{text_color}">#{label}</text>)
      ].join("\n")
    end.join("\n")

    <<~SVG
      <svg xmlns="http://www.w3.org/2000/svg" width="#{width}" height="#{height}" viewBox="0 0 #{width} #{height}">
        <rect width="#{width}" height="#{height}" fill="#fcfbf7" />
        <text x="#{padding}" y="34" font-family="Verdana, sans-serif" font-size="24" font-weight="700" fill="#111827">Hack N Slash Level #{level[:size]}x#{level[:size]}</text>
        <text x="#{padding}" y="58" font-family="Verdana, sans-serif" font-size="14" fill="#374151">Seed #{level[:seed]} | E entree | S sortie | A-B joueurs | 1-7 monstres</text>
        #{cells}
      </svg>
    SVG
  end

  def cell_payload(cell)
    { x: cell.x, y: cell.y }
  end

  def key_for(cell)
    "#{cell.x},#{cell.y}"
  end

  def same_cell?(left, right)
    left.x == right.x && left.y == right.y
  end

  def distance_between(left, right)
    (left.x - right.x).abs + (left.y - right.y).abs
  end

  def size
    grid_size
  end
end

if $PROGRAM_NAME == __FILE__
  size = (ARGV[0] || 5).to_i
  seed = ARGV[1] ? ARGV[1].to_i : Random.new_seed
  output_dir = ARGV[2] || 'generated_levels'

  generator = LevelGenerator.new(size: size, seed: seed)
  level = generator.generate
  level[:svg_path] = generator.export_svg(level, output_dir: output_dir)
  puts JSON.pretty_generate(level)
end

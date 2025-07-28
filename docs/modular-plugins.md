# Modular Plugin Structure

The Semantic Kernel PHP framework supports a modular plugin structure where each skill has its own directory with individual configuration and prompt files.

## Structure

```
plugins/
└── PluginName/
    ├── skill1/
    │   ├── config.json
    │   └── skprompt.txt
    ├── skill2/
    │   ├── config.json
    │   └── skprompt.txt
    └── skill3/
        ├── config.json
        └── skprompt.txt
```

## Benefits

### 📦 **Modularity**
- Each skill is completely self-contained
- Clear separation of concerns
- Independent development and testing

### 🔄 **Reusability**
- Skills can be shared across different plugins
- Easy to copy/move skills between projects
- Plugin marketplace compatibility

### 📈 **Versioning**
- Independent version control per skill
- Granular updates and releases
- Better dependency management

### 👥 **Collaboration**
- Team members can work on different skills simultaneously
- No merge conflicts in configuration files
- Clear ownership and responsibility

### 🧪 **Testing**
- Easier to test individual skills
- Isolated debugging and troubleshooting
- Unit testing per skill

## Configuration Format

Each skill's `config.json` must include:

```json
{
  "schema": 1,
  "name": "skill_name",
  "type": "semantic",
  "description": "What this skill does",
  "version": "1.0.0",
  "author": "Your Name",
  "plugin": "PluginName",
  "parameters": {
    "input": {
      "description": "Input parameter description",
      "type": "string",
      "required": true
    }
  },
  "execution_settings": {
    "max_tokens": 150,
    "temperature": 0.3,
    "top_p": 1.0
  },
  "prompt_file": "skprompt.txt",
  "tags": ["tag1", "tag2"]
}
```

### Required Fields

| Field | Description |
|-------|-------------|
| `name` | Unique skill name (used as function name) |
| `type` | Plugin type: `semantic` or `native` |
| `description` | What the skill does |
| `plugin` | Plugin name (groups skills together) |

### Optional Fields

| Field | Description | Default |
|-------|-------------|---------|
| `version` | Skill version | `1.0.0` |
| `author` | Skill author | - |
| `parameters` | Input parameters definition | `{}` |
| `execution_settings` | AI model settings | `{}` |
| `prompt_file` | Prompt template filename | `skprompt.txt` |
| `tags` | Skill categorization | `[]` |

## Framework Support

The `PluginLoader` automatically:

### 🔍 **Discovery**
- Recursively scans directories for `config.json` files
- Groups skills by `plugin` field
- Validates configuration structure

### 🏷️ **Grouping**
- Creates one plugin per unique `plugin` name
- Combines multiple skills into single plugin
- Maintains skill independence

### ✅ **Validation**
- Validates JSON syntax and structure
- Checks required fields
- Verifies prompt file existence
- Logs detailed error messages

### 💾 **Caching**
- Caches discovery results for performance
- Intelligent cache invalidation
- Configurable cache settings

## Usage Example

```php
<?php
use SemanticKernel\Plugins\PluginLoader;

// Auto-discover modular plugins
$loader = new PluginLoader();
$plugins = $loader->discoverPlugins('./plugins');

// Import into kernel
foreach ($plugins as $plugin) {
    $kernel->importPlugin($plugin);
}

// Use individual skills
$result = $kernel->run('WritingSkills.summarize', $context);
$result = $kernel->run('WritingSkills.translate', $context);
```

## Migration from Monolithic

### Old Structure (Monolithic)
```
plugins/
└── WritingSkills/
    ├── config.json           # Single config for all functions
    ├── summarize.skprompt.txt
    └── translate.skprompt.txt
```

### New Structure (Modular)
```
plugins/
└── WritingSkills/
    ├── summarize/
    │   ├── config.json       # Individual config per skill
    │   └── skprompt.txt
    └── translate/
        ├── config.json       # Individual config per skill
        └── skprompt.txt
```

### Migration Steps

1. **Create skill directories**:
   ```bash
   mkdir -p plugins/WritingSkills/summarize
   mkdir -p plugins/WritingSkills/translate
   ```

2. **Split configuration**: Extract each function from the monolithic config into individual skill configs

3. **Move prompt files**: Move and rename prompt files to match the new structure

4. **Test discovery**: Verify the framework discovers the new structure correctly

## Best Practices

### 📁 **Organization**
- Use descriptive skill names
- Group related skills in the same plugin
- Keep consistent naming conventions

### 📝 **Configuration**
- Include comprehensive parameter descriptions
- Set appropriate execution settings
- Use semantic versioning

### 🤖 **Prompts**
- Write clear, specific prompts
- Include examples in prompts when helpful
- Use consistent variable naming

### 🔧 **Development**
- Test skills individually
- Use version control per skill
- Document skill purpose and usage

## Examples

See the following for practical usage:

- `examples/03_semantic_functions.php` - Plugin creation and usage demo
- `plugins/WritingSkills/` - Real modular plugin implementation
- `docs/cookbook.md` - Copy-paste solutions using modular plugins

## Framework Compatibility

The modular structure is:
- ✅ **Backward compatible** - Monolithic plugins still work
- ✅ **Auto-detected** - No configuration needed
- ✅ **Mixed support** - Can use both structures simultaneously
- ✅ **Performance optimized** - Intelligent caching and discovery 
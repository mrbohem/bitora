#!/bin/bash
set -e

echo "Setting up Yakpro-Po obfuscator..."

# Check if yakpro-po exists
if [ ! -d "tools/yakpro-po" ]; then
    echo "Cloning Yakpro-Po..."
    mkdir -p tools
    git clone https://github.com/pk-fr/yakpro-po.git tools/yakpro-po
fi

cd tools/yakpro-po

# Clone PHP-Parser dependency
if [ ! -d "PHP-Parser" ]; then
    echo "Installing PHP-Parser dependency..."
    git clone https://github.com/nikic/PHP-Parser.git
fi

# Create yakpro-po.cnf configuration
cat > yakpro-po.cnf <<'EOF'
<?php
// YAK Pro - Php Obfuscator: Config File

$conf->t_scramble_mode          = 'identifier';
$conf->t_obfuscate_constant_name = true;
$conf->t_obfuscate_variable_name = true;
$conf->t_obfuscate_function_name = true;
$conf->t_obfuscate_class_name    = true;
$conf->t_obfuscate_interface_name= true;
$conf->t_obfuscate_trait_name    = true;
$conf->t_obfuscate_property_name = true;
$conf->t_obfuscate_method_name   = true;
$conf->t_obfuscate_namespace_name= true;
$conf->t_obfuscate_label_name    = true;

$conf->t_shuffle_statements     = true;
$conf->t_strip_comments         = true;

$conf->t_skip_directories = [
    'vendor',
    'node_modules',
    'storage',
    'bootstrap/cache',
    'public/vendor',
    'database/migrations',
];

$conf->t_skip_files = [
    'server.php',
];
EOF

echo "Yakpro-Po setup complete!"
echo "Config file created at: $(pwd)/yakpro-po.cnf"

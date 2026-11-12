<?php

// Schematic layout in metres. These are configurable design dimensions, not a survey.
// Location matching always uses the inventory's exact floor/room pair.
return [
    'version' => 1,
    'name' => 'Planta principal',
    'schematic' => true,
    'rooms' => [
        ['id' => 'ops-a', 'location_id' => 1, 'floor' => 'Planta principal', 'location' => 'Área administrativa abierta — sector A', 'label' => 'Operaciones · A', 'purpose' => 'Personal operativo · sector A', 'x' => 0, 'z' => 0, 'width' => 15, 'depth' => 10, 'color' => '#e0e9e7', 'open_south' => true],
        ['id' => 'ops-b', 'location_id' => 2, 'floor' => 'Planta principal', 'location' => 'Área administrativa abierta — sector B', 'label' => 'Operaciones · B', 'purpose' => 'Personal operativo · sector B', 'x' => 0, 'z' => 10, 'width' => 15, 'depth' => 9, 'color' => '#e0e9e7', 'open_north' => true],
        ['id' => 'technical', 'location_id' => 7, 'floor' => 'Planta principal', 'location' => 'Sala de personal técnico', 'label' => 'Equipo técnico', 'purpose' => 'Soporte técnico y diagnóstico', 'x' => 17, 'z' => 0, 'width' => 10, 'depth' => 7, 'color' => '#e0e8f1'],
        ['id' => 'management', 'location_id' => 5, 'floor' => 'Planta principal', 'location' => 'Oficina de gerencia y coordinación', 'label' => 'Gerencia y coordinación', 'purpose' => 'Gestión y coordinación del personal', 'x' => 17, 'z' => 7, 'width' => 10, 'depth' => 7, 'color' => '#ece5db'],
        ['id' => 'it', 'location_id' => 4, 'floor' => 'Planta principal', 'location' => 'Sala de servidores / IT', 'label' => 'Infraestructura IT', 'purpose' => 'Protección eléctrica, CCTV y climatización', 'x' => 17, 'z' => 14, 'width' => 5, 'depth' => 5, 'color' => '#dce4ee'],
        ['id' => 'admin', 'location_id' => 6, 'floor' => 'Planta principal', 'location' => 'Administración y recursos humanos', 'label' => 'Apoyo administrativo', 'purpose' => 'Impresión compartida', 'x' => 22, 'z' => 14, 'width' => 5, 'depth' => 5, 'color' => '#ece5db'],
        ['id' => 'reception', 'location_id' => 3, 'floor' => 'Planta principal', 'location' => 'Atención al cliente', 'label' => 'Recepción y espera', 'purpose' => 'Acceso y atención al cliente', 'x' => 0, 'z' => 21, 'width' => 17, 'depth' => 5, 'color' => '#e9e5dc'],
        ['id' => 'perimeter', 'location_id' => 8, 'floor' => 'Exterior', 'location' => 'Perímetro y accesos', 'label' => 'Accesos · CCTV', 'purpose' => 'Seguridad exterior', 'x' => 19, 'z' => 21, 'width' => 8, 'depth' => 5, 'color' => '#dce8dc', 'exterior' => true],
    ],
];

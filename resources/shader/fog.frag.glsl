#version 330 core

in vec2 v_uv;
out vec4 fragment_color;

uniform sampler2D u_color_texture;
uniform sampler2D u_position_texture;

uniform vec3 u_camera_pos;

void main() {             
    vec2 uv = vec2(v_uv.x, v_uv.y);

    vec4 color = texture(u_color_texture, uv);
    vec3 vertpos = texture(u_position_texture, uv).xyz;

    // fog effect
    float dist = length(u_camera_pos - vertpos);
    if (vertpos.x == 0.0 && vertpos.y == 0.0 && vertpos.z == 0.0) {
        dist = 1000;
    }
    
    // after 128 units start fading out
    // till 256 units
    float fade_start = 32.0;
    float fade_end = 64.0;
    if (dist > fade_start) {
        float fog_factor = (dist - fade_start) / (fade_end - fade_start);
        fog_factor = clamp(fog_factor, 0.0, 1.0) * 0.5;
        // fog is light blue
        color = mix(color, vec4(0.7, 0.7, 1.0, 1.0), fog_factor);
    }
    // general fog effect


    fragment_color = color;
}
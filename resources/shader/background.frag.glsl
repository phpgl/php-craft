#version 330 core

in vec2 v_uv;
out vec4 fragment_color;
uniform sampler2D u_texture;

void main() {             
    vec2 uv = vec2(v_uv.x, 1.0 - v_uv.y);
    fragment_color = texture(u_texture, uv);
}
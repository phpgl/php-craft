#version 330 core

layout(location = 0) out vec4 frag_color;
layout(location = 1) out vec3 frag_position;

in vec3 v_position;
in vec3 v_normal;
in vec2 v_uv;
in float v_blocktype;

uniform sampler2D u_texture;

struct Sun
{
    vec3 diffuse;
    vec3 direction;
};

uniform Sun u_sun;

void main()
{
    // we have a 10x10 texture atlas
    // the blocktype represents the index of the texture in the atlas
    float atlasSize = 10.0;
    float cellX = mod(v_blocktype, atlasSize);
    float cellY = floor(v_blocktype / atlasSize);
    vec2 uvOffset = vec2(cellX / atlasSize, cellY / atlasSize);
    vec2 uvScale = vec2(1.0 / atlasSize, 1.0 / -atlasSize);

    vec2 atlasUV = uvScale * v_uv + uvOffset;
    vec4 color = texture(u_texture, atlasUV);

    vec3 lightDir = normalize(u_sun.direction);
    float lightIntensity = max(dot(v_normal, lightDir), 0.0);
    vec3 ambientColor = vec3(0.2, 0.2, 0.2);
    vec3 finalColor = color.rgb * (lightIntensity * u_sun.diffuse + ambientColor);

    frag_color = vec4(finalColor, color.a);
    frag_position = v_position;
}
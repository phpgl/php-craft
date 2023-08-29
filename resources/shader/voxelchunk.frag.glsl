#version 330 core

layout(location = 0) out vec4 frag_color;

in vec3 v_position;
in vec3 v_normal;
in vec2 v_uv;
in float v_blocktype;

uniform sampler2D u_texture;

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

    vec3 lightDir = normalize(vec3(0.5, 1.0, 0.5));
    float lightIntensity = max(dot(v_normal, lightDir), 0.0);
    vec3 lightColor = vec3(1.0, 1.0, 1.0);
    vec3 ambientColor = vec3(0.2, 0.2, 0.2);
    vec3 finalColor = color.rgb * (lightIntensity * lightColor + ambientColor);

    frag_color = vec4(finalColor, color.a);
}
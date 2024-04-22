#version 330 core

layout (location = 0) in vec3 a_position;

out vec3 vertex_position;

uniform mat4 projection;
uniform mat4 view;

void main()
{
    vec4 pos = inverse(mat4(mat3(view))) * inverse(projection) * vec4(a_position.xy, -1.0, 1.0);
    vertex_position = pos.xyz;
    
    gl_Position = vec4(a_position.xy, 1.0, 1.0);
}  